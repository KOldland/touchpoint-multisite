(function (wp) {
  if (!wp || !wp.data || !wp.hooks || !wp.element || !wp.compose) {
    return;
  }

  var NOTICE_NAMESPACE = 'touchpoint-editor-guidance';
  var LONG_PARAGRAPH_THRESHOLD = 500;

  function stripTags(html) {
    if (!html) {
      return '';
    }
    return html.replace(/<[^>]*>/g, ' ');
  }

  function countWords(text) {
    var trimmed = (text || '').trim();
    if (!trimmed) {
      return 0;
    }
    return trimmed.split(/\s+/).length;
  }

  function flattenBlocks(blocks, output) {
    output = output || [];
    (blocks || []).forEach(function (block) {
      output.push(block);
      if (block.innerBlocks && block.innerBlocks.length) {
        flattenBlocks(block.innerBlocks, output);
      }
    });
    return output;
  }

  function analyzeBlocks(blocks) {
    var issuesByClientId = {};
    var notices = [];
    var headings = [];

    blocks.forEach(function (block) {
      if (block.name === 'core/paragraph') {
        var wordCount = countWords(stripTags(block.attributes.content));
        if (wordCount > LONG_PARAGRAPH_THRESHOLD) {
          issuesByClientId[block.clientId] = issuesByClientId[block.clientId] || {};
          issuesByClientId[block.clientId].paragraphLong = true;
          notices.push({
            id: NOTICE_NAMESPACE + '-paragraph-' + block.clientId,
            message: 'This paragraph exceeds 500 words. Consider adding a pull quote.'
          });
        }
      }

      if (block.name === 'core/heading') {
        headings.push({
          clientId: block.clientId,
          level: parseInt(block.attributes.level, 10) || 2
        });
      }
    });

    var h1Count = headings.filter(function (heading) {
      return heading.level === 1;
    }).length;

    var previousHeadingLevel = null;
    headings.forEach(function (heading) {
      if (h1Count > 1 && heading.level === 1) {
        issuesByClientId[heading.clientId] = issuesByClientId[heading.clientId] || {};
        issuesByClientId[heading.clientId].headingRogue = true;
        notices.push({
          id: NOTICE_NAMESPACE + '-heading-h1-' + heading.clientId,
          message: 'Multiple H1 headings found. Use the post title for H1 and H2 for primary sections.'
        });
      }

      if (heading.level >= 4) {
        issuesByClientId[heading.clientId] = issuesByClientId[heading.clientId] || {};
        issuesByClientId[heading.clientId].headingRogue = true;
        notices.push({
          id: NOTICE_NAMESPACE + '-heading-level-' + heading.clientId,
          message: 'Headings should be H2 or H3 only. Avoid H4-H6.'
        });
      }

      if (previousHeadingLevel && heading.level > previousHeadingLevel + 1) {
        issuesByClientId[heading.clientId] = issuesByClientId[heading.clientId] || {};
        issuesByClientId[heading.clientId].headingSkip = true;
        notices.push({
          id: NOTICE_NAMESPACE + '-heading-skip-' + heading.clientId,
          message: 'Heading levels should not skip. Use sequential levels (H2 to H3).'
        });
      }

      previousHeadingLevel = heading.level;
    });

    return {
      issuesByClientId: issuesByClientId,
      notices: notices
    };
  }

  var activeNoticeIds = {};
  var latestIssuesByClientId = {};

  function syncNotices(notices) {
    var noticeStore = wp.data.dispatch('core/notices');
    var nextIds = {};

    notices.forEach(function (notice) {
      nextIds[notice.id] = true;
      if (!activeNoticeIds[notice.id]) {
        noticeStore.createNotice('warning', notice.message, {
          id: notice.id,
          isDismissible: true
        });
      }
    });

    Object.keys(activeNoticeIds).forEach(function (id) {
      if (!nextIds[id]) {
        noticeStore.removeNotice(id);
      }
    });

    activeNoticeIds = nextIds;
  }

  function refreshIssues() {
    var blocks = flattenBlocks(wp.data.select('core/block-editor').getBlocks());
    var analysis = analyzeBlocks(blocks);
    latestIssuesByClientId = analysis.issuesByClientId;
    syncNotices(analysis.notices);
  }

  wp.data.subscribe(refreshIssues);
  refreshIssues();

  var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
  var addFilter = wp.hooks.addFilter;
  var createElement = wp.element.createElement;
  var useSelect = wp.data.useSelect;

  var withGuidanceClasses = createHigherOrderComponent(function (BlockListBlock) {
    return function (props) {
      useSelect(function (select) {
        return select('core/block-editor').getBlocks();
      }, []);

      var issues = latestIssuesByClientId[props.clientId] || {};
      var extraClasses = [];

      if (issues.paragraphLong) {
        extraClasses.push('touchpoint-guidance-paragraph-long');
      }
      if (issues.headingRogue) {
        extraClasses.push('touchpoint-guidance-heading-rogue');
      }
      if (issues.headingSkip) {
        extraClasses.push('touchpoint-guidance-heading-skip');
      }

      if (!extraClasses.length) {
        return createElement(BlockListBlock, props);
      }

      var wrapperProps = Object.assign({}, props.wrapperProps, {
        className: [props.wrapperProps && props.wrapperProps.className, extraClasses.join(' ')].filter(Boolean).join(' ')
      });

      return createElement(BlockListBlock, Object.assign({}, props, { wrapperProps: wrapperProps }));
    };
  }, 'withGuidanceClasses');

  addFilter('editor.BlockListBlock', 'touchpoint/guidance-classes', withGuidanceClasses);
})(window.wp);
