function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regenerator() { var e, t, r = "function" == typeof Symbol ? Symbol : {}, n = r.iterator || "@@iterator", o = r.toStringTag || "@@toStringTag"; function i(r, n, o, i) { var c = n && n.prototype instanceof Generator ? n : Generator, u = Object.create(c.prototype); return _regeneratorDefine2(u, "_invoke", function (r, n, o) { var i, c, u, f = 0, p = o || [], y = !1, G = { p: 0, n: 0, v: e, a: d, f: d.bind(e, 4), d: function d(t, r) { return i = t, c = 0, u = e, G.n = r, a; } }; function d(r, n) { for (c = r, u = n, t = 0; !y && f && !o && t < p.length; t++) { var o, i = p[t], d = G.p, l = i[2]; r > 3 ? (o = l === n) && (u = i[(c = i[4]) ? 5 : (c = 3, 3)], i[4] = i[5] = e) : i[0] <= d && ((o = r < 2 && d < i[1]) ? (c = 0, G.v = n, G.n = i[1]) : d < l && (o = r < 3 || i[0] > n || n > l) && (i[4] = r, i[5] = n, G.n = l, c = 0)); } if (o || r > 1) return a; throw y = !0, n; } return function (o, p, l) { if (f > 1) throw TypeError("Generator is already running"); for (y && 1 === p && d(p, l), c = p, u = l; (t = c < 2 ? e : u) || !y;) { i || (c ? c < 3 ? (c > 1 && (G.n = -1), d(c, u)) : G.n = u : G.v = u); try { if (f = 2, i) { if (c || (o = "next"), t = i[o]) { if (!(t = t.call(i, u))) throw TypeError("iterator result is not an object"); if (!t.done) return t; u = t.value, c < 2 && (c = 0); } else 1 === c && (t = i.return) && t.call(i), c < 2 && (u = TypeError("The iterator does not provide a '" + o + "' method"), c = 1); i = e; } else if ((t = (y = G.n < 0) ? u : r.call(n, G)) !== a) break; } catch (t) { i = e, c = 1, u = t; } finally { f = 1; } } return { value: t, done: y }; }; }(r, o, i), !0), u; } var a = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} t = Object.getPrototypeOf; var c = [][n] ? t(t([][n]())) : (_regeneratorDefine2(t = {}, n, function () { return this; }), t), u = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(c); function f(e) { return Object.setPrototypeOf ? Object.setPrototypeOf(e, GeneratorFunctionPrototype) : (e.__proto__ = GeneratorFunctionPrototype, _regeneratorDefine2(e, o, "GeneratorFunction")), e.prototype = Object.create(u), e; } return GeneratorFunction.prototype = GeneratorFunctionPrototype, _regeneratorDefine2(u, "constructor", GeneratorFunctionPrototype), _regeneratorDefine2(GeneratorFunctionPrototype, "constructor", GeneratorFunction), GeneratorFunction.displayName = "GeneratorFunction", _regeneratorDefine2(GeneratorFunctionPrototype, o, "GeneratorFunction"), _regeneratorDefine2(u), _regeneratorDefine2(u, o, "Generator"), _regeneratorDefine2(u, n, function () { return this; }), _regeneratorDefine2(u, "toString", function () { return "[object Generator]"; }), (_regenerator = function _regenerator() { return { w: i, m: f }; })(); }
function _regeneratorDefine2(e, r, n, t) { var i = Object.defineProperty; try { i({}, "", {}); } catch (e) { i = 0; } _regeneratorDefine2 = function _regeneratorDefine(e, r, n, t) { function o(r, n) { _regeneratorDefine2(e, r, function (e) { return this._invoke(r, n, e); }); } r ? i ? i(e, r, { value: n, enumerable: !t, configurable: !t, writable: !t }) : e[r] = n : (o("next", 0), o("throw", 1), o("return", 2)); }, _regeneratorDefine2(e, r, n, t); }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t.return && (u = t.return(), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }
var registerPlugin = wp.plugins.registerPlugin;
var _wp$editPost = wp.editPost,
  PluginSidebar = _wp$editPost.PluginSidebar,
  PluginSidebarMoreMenuItem = _wp$editPost.PluginSidebarMoreMenuItem;
var _wp$components = wp.components,
  PanelBody = _wp$components.PanelBody,
  TextareaControl = _wp$components.TextareaControl,
  Button = _wp$components.Button,
  Spinner = _wp$components.Spinner;
var _wp$element = wp.element,
  useState = _wp$element.useState,
  useEffect = _wp$element.useEffect;
var _wp$data = wp.data,
  useSelect = _wp$data.useSelect,
  useDispatch = _wp$data.useDispatch;
var _wp = wp,
  apiFetch = _wp.apiFetch;
var DualGPTSidebar = function DualGPTSidebar() {
  var _dualGptData, _dualGptData2, _dualGptData3, _dualGptData4;
  var _useState = useState(''),
    _useState2 = _slicedToArray(_useState, 2),
    researchPrompt = _useState2[0],
    setResearchPrompt = _useState2[1];
  var _useState3 = useState('draft'),
    _useState4 = _slicedToArray(_useState3, 2),
    authorMode = _useState4[0],
    setAuthorMode = _useState4[1];
  var _useState5 = useState(''),
    _useState6 = _slicedToArray(_useState5, 2),
    frameworkBriefId = _useState6[0],
    setFrameworkBriefId = _useState6[1];
  var _useState7 = useState(''),
    _useState8 = _slicedToArray(_useState7, 2),
    plannerSessionId = _useState8[0],
    setPlannerSessionId = _useState8[1];
  var _useState9 = useState(''),
    _useState0 = _slicedToArray(_useState9, 2),
    authorInstructions = _useState0[0],
    setAuthorInstructions = _useState0[1];
  var _useState1 = useState({
      industry_focus: ((_dualGptData = dualGptData) === null || _dualGptData === void 0 || (_dualGptData = _dualGptData.coreSettings) === null || _dualGptData === void 0 ? void 0 : _dualGptData.industry_focus) || 'General',
      audience_tier: ((_dualGptData2 = dualGptData) === null || _dualGptData2 === void 0 || (_dualGptData2 = _dualGptData2.coreSettings) === null || _dualGptData2 === void 0 ? void 0 : _dualGptData2.audience_tier) || 'General',
      risk_tolerance: ((_dualGptData3 = dualGptData) === null || _dualGptData3 === void 0 || (_dualGptData3 = _dualGptData3.coreSettings) === null || _dualGptData3 === void 0 ? void 0 : _dualGptData3.risk_tolerance) || 'Moderate',
      brand_profile: ((_dualGptData4 = dualGptData) === null || _dualGptData4 === void 0 || (_dualGptData4 = _dualGptData4.coreSettings) === null || _dualGptData4 === void 0 ? void 0 : _dualGptData4.brand_profile) || 'Brand A (FSI)'
    }),
    _useState10 = _slicedToArray(_useState1, 2),
    authorCoreSettings = _useState10[0],
    setAuthorCoreSettings = _useState10[1];
  var _useState11 = useState(false),
    _useState12 = _slicedToArray(_useState11, 2),
    researchLoading = _useState12[0],
    setResearchLoading = _useState12[1];
  var _useState13 = useState(false),
    _useState14 = _slicedToArray(_useState13, 2),
    authorLoading = _useState14[0],
    setAuthorLoading = _useState14[1];
  var _useState15 = useState(''),
    _useState16 = _slicedToArray(_useState15, 2),
    researchResults = _useState16[0],
    setResearchResults = _useState16[1];
  var _useState17 = useState(''),
    _useState18 = _slicedToArray(_useState17, 2),
    authorResults = _useState18[0],
    setAuthorResults = _useState18[1];
  var _useState19 = useState(''),
    _useState20 = _slicedToArray(_useState19, 2),
    researchError = _useState20[0],
    setResearchError = _useState20[1];
  var _useState21 = useState(''),
    _useState22 = _slicedToArray(_useState21, 2),
    authorError = _useState22[0],
    setAuthorError = _useState22[1];
  var _useState23 = useState(null),
    _useState24 = _slicedToArray(_useState23, 2),
    researchJobId = _useState24[0],
    setResearchJobId = _useState24[1];
  var _useState25 = useState(null),
    _useState26 = _slicedToArray(_useState25, 2),
    authorJobId = _useState26[0],
    setAuthorJobId = _useState26[1];
  var _useState27 = useState([]),
    _useState28 = _slicedToArray(_useState27, 2),
    authorBlocks = _useState28[0],
    setAuthorBlocks = _useState28[1];
  var _useState29 = useState(null),
    _useState30 = _slicedToArray(_useState29, 2),
    authorAbstract = _useState30[0],
    setAuthorAbstract = _useState30[1];
  var _useState31 = useState([]),
    _useState32 = _slicedToArray(_useState31, 2),
    authorWarnings = _useState32[0],
    setAuthorWarnings = _useState32[1];
  var _useState33 = useState([]),
    _useState34 = _slicedToArray(_useState33, 2),
    authorValidationErrors = _useState34[0],
    setAuthorValidationErrors = _useState34[1];
  var _useDispatch = useDispatch('core/block-editor'),
    insertBlocks = _useDispatch.insertBlocks;
  var _useDispatch2 = useDispatch('core/notices'),
    createNotice = _useDispatch2.createNotice;
  var draftContent = useSelect(function (select) {
    return select('core/editor').getEditedPostContent();
  }, []);
  var handleResearchSubmit = function () {
    var _ref = _asyncToGenerator(_regenerator().m(function _callee() {
      var sessionResponse, jobResponse, errorMessage, _t;
      return _regenerator().w(function (_context) {
        while (1) switch (_context.p = _context.n) {
          case 0:
            if (researchPrompt.trim()) {
              _context.n = 1;
              break;
            }
            setResearchError('Please enter a research prompt');
            return _context.a(2);
          case 1:
            setResearchLoading(true);
            setResearchError('');
            setResearchResults('');
            _context.p = 2;
            _context.n = 3;
            return apiFetch({
              path: 'dual-gpt/v1/sessions',
              method: 'POST',
              data: {
                role: 'research',
                title: 'Research Session - ' + new Date().toLocaleString()
              }
            });
          case 3:
            sessionResponse = _context.v;
            _context.n = 4;
            return apiFetch({
              path: 'dual-gpt/v1/jobs',
              method: 'POST',
              data: {
                session_id: sessionResponse.session_id,
                prompt: researchPrompt,
                model: 'gpt-4'
              }
            });
          case 4:
            jobResponse = _context.v;
            setResearchJobId(jobResponse.job_id);
            setResearchResults('Job submitted successfully. Processing...');
            _pollJobStatus(jobResponse.job_id, 'research');
            _context.n = 6;
            break;
          case 5:
            _context.p = 5;
            _t = _context.v;
            console.error('Research error:', _t);
            errorMessage = 'An error occurred while processing your research request.';
            if (_t.code === 'budget_exceeded') {
              errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (_t.code === 'invalid_api_key') {
              errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (_t.message) {
              errorMessage = _t.message;
            }
            setResearchError(errorMessage);
            createNotice('error', errorMessage, {
              type: 'snackbar'
            });
          case 6:
            _context.p = 6;
            setResearchLoading(false);
            return _context.f(6);
          case 7:
            return _context.a(2);
        }
      }, _callee, null, [[2, 5, 6, 7]]);
    }));
    return function handleResearchSubmit() {
      return _ref.apply(this, arguments);
    };
  }();
  var handleAuthorSubmit = function () {
    var _ref2 = _asyncToGenerator(_regenerator().m(function _callee2() {
      var payload, response, _response$output, _response$output2, _response$output3, errorMessage, _t2;
      return _regenerator().w(function (_context2) {
        while (1) switch (_context2.p = _context2.n) {
          case 0:
            setAuthorLoading(true);
            setAuthorError('');
            setAuthorResults('');
            setAuthorBlocks([]);
            setAuthorAbstract(null);
            setAuthorWarnings([]);
            setAuthorValidationErrors([]);
            _context2.p = 1;
            payload = {
              mode: authorMode,
              framework_brief_id: frameworkBriefId || undefined,
              planner_session_id: plannerSessionId || undefined,
              draft_content: authorMode !== 'draft' ? draftContent : undefined,
              instructions: authorInstructions || undefined,
              core_settings: authorCoreSettings
            };
            _context2.n = 2;
            return apiFetch({
              path: 'dual-gpt/v1/author/run',
              method: 'POST',
              data: payload
            });
          case 2:
            response = _context2.v;
            setAuthorWarnings(response.warnings || []);
            setAuthorValidationErrors(response.validation_errors || []);
            if (response.mode === 'draft') {
              setAuthorBlocks(((_response$output = response.output) === null || _response$output === void 0 ? void 0 : _response$output.blocks) || []);
              setAuthorResults('Draft completed successfully.');
            } else if (response.mode === 'abstract') {
              setAuthorAbstract(((_response$output2 = response.output) === null || _response$output2 === void 0 ? void 0 : _response$output2.abstract) || null);
              setAuthorResults('Abstract completed successfully.');
            } else if (response.mode === 'enrichment') {
              setAuthorBlocks(((_response$output3 = response.output) === null || _response$output3 === void 0 ? void 0 : _response$output3.blocks) || []);
              setAuthorResults('Enrichment completed successfully.');
            }
            _context2.n = 4;
            break;
          case 3:
            _context2.p = 3;
            _t2 = _context2.v;
            console.error('Author error:', _t2);
            errorMessage = 'An error occurred while processing your authoring request.';
            if (_t2.code === 'budget_exceeded') {
              errorMessage = 'Token budget exceeded. Please contact an administrator.';
            } else if (_t2.code === 'invalid_api_key') {
              errorMessage = 'API configuration error. Please contact an administrator.';
            } else if (_t2.message) {
              errorMessage = _t2.message;
            }
            setAuthorError(errorMessage);
            createNotice('error', errorMessage, {
              type: 'snackbar'
            });
          case 4:
            _context2.p = 4;
            setAuthorLoading(false);
            return _context2.f(4);
          case 5:
            return _context2.a(2);
        }
      }, _callee2, null, [[1, 3, 4, 5]]);
    }));
    return function handleAuthorSubmit() {
      return _ref2.apply(this, arguments);
    };
  }();
  var _pollJobStatus = function () {
    var _ref3 = _asyncToGenerator(_regenerator().m(function _callee3(jobId, type) {
      var response, errorMsg, _errorMsg, _t3;
      return _regenerator().w(function (_context3) {
        while (1) switch (_context3.p = _context3.n) {
          case 0:
            _context3.p = 0;
            _context3.n = 1;
            return apiFetch({
              path: "dual-gpt/v1/jobs/".concat(jobId),
              method: 'GET'
            });
          case 1:
            response = _context3.v;
            if (response.status === 'completed') {
              if (type === 'research') {
                setResearchResults('Research completed successfully!');
              } else {
                setAuthorResults('Content generation completed successfully!');
              }
            } else if (response.status === 'failed') {
              errorMsg = response.error_message || 'Job failed';
              if (type === 'research') {
                setResearchError(errorMsg);
              } else {
                setAuthorError(errorMsg);
              }
              createNotice('error', "Job failed: ".concat(errorMsg), {
                type: 'snackbar'
              });
            } else {
              setTimeout(function () {
                return _pollJobStatus(jobId, type);
              }, 2000);
            }
            _context3.n = 3;
            break;
          case 2:
            _context3.p = 2;
            _t3 = _context3.v;
            console.error('Polling error:', _t3);
            _errorMsg = 'Error checking job status';
            if (type === 'research') {
              setResearchError(_errorMsg);
            } else {
              setAuthorError(_errorMsg);
            }
          case 3:
            return _context3.a(2);
        }
      }, _callee3, null, [[0, 2]]);
    }));
    return function pollJobStatus(_x, _x2) {
      return _ref3.apply(this, arguments);
    };
  }();
  var insertBlocksFromAuthor = function insertBlocksFromAuthor() {
    if (!authorBlocks || authorBlocks.length === 0) {
      return;
    }
    var escapeHtml = function escapeHtml(value) {
      if (value === null || value === undefined) {
        return '';
      }
      return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };
    var buildPullquoteMetaSpan = function buildPullquoteMetaSpan(meta) {
      if (!meta || _typeof(meta) !== 'object') {
        return '';
      }
      var attributes = ["data-source-author=\"".concat(escapeHtml(meta.source_author || ''), "\""), "data-publication=\"".concat(escapeHtml(meta.publication || ''), "\""), "data-organisation=\"".concat(escapeHtml(meta.organisation || ''), "\""), "data-date=\"".concat(escapeHtml(meta.date || ''), "\""), "data-citation-ref-id=\"".concat(escapeHtml(meta.citation_ref_id || ''), "\"")];
      return "<span class=\"dual-gpt-pullquote-meta\" style=\"display:none\" ".concat(attributes.join(' '), "></span>");
    };
    var blocks = authorBlocks.map(function (block) {
      switch (block.type) {
        case 'heading':
          return wp.blocks.createBlock('core/heading', {
            level: block.level || 2,
            content: block.content || ''
          });
        case 'paragraph':
          return wp.blocks.createBlock('core/paragraph', {
            content: block.content || ''
          });
        case 'list':
          var listItems = (block.items || []).map(function (item) {
            return "<li>".concat(item, "</li>");
          }).join('');
          var listTag = block.ordered ? 'ol' : 'ul';
          return wp.blocks.createBlock('core/list', {
            ordered: !!block.ordered,
            values: "<".concat(listTag, ">").concat(listItems, "</").concat(listTag, ">")
          });
        case 'pullquote':
          var pullquoteMeta = buildPullquoteMetaSpan(block.meta || block.metadata);
          return wp.blocks.createBlock('core/pullquote', {
            value: "<p>".concat(block.content || '', "</p>").concat(pullquoteMeta),
            citation: block.cite || ''
          });
        case 'quote':
          return wp.blocks.createBlock('core/quote', {
            value: "<p>".concat(block.content || '', "</p>"),
            citation: block.cite || ''
          });
        case 'separator':
          return wp.blocks.createBlock('core/separator', {});
        default:
          return wp.blocks.createBlock('core/paragraph', {
            content: block.content || ''
          });
      }
    });
    insertBlocks(blocks);
  };
  return wp.element.createElement(PluginSidebar, {
    name: "dual-gpt-sidebar",
    title: "Dual-GPT Authoring",
    icon: "admin-tools"
  }, wp.element.createElement(PanelBody, {
    title: "Research Pane",
    initialOpen: true
  }, wp.element.createElement(TextareaControl, {
    label: "Research Prompt",
    value: researchPrompt,
    onChange: function onChange(value) {
      setResearchPrompt(value);
      if (researchError) setResearchError('');
    },
    placeholder: "Enter your research query..."
  }), wp.element.createElement(Button, {
    isPrimary: true,
    onClick: handleResearchSubmit,
    disabled: researchLoading || !researchPrompt.trim()
  }, researchLoading ? wp.element.createElement(Spinner, null) : 'Research'), researchError && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#ffe6e6',
      border: '1px solid #ff9999',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Error:"), " ", researchError), researchResults && !researchError && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#e6ffe6',
      border: '1px solid #99ff99',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Research Results:"), wp.element.createElement("p", null, researchResults))), wp.element.createElement(PanelBody, {
    title: "Author Agent",
    initialOpen: true
  }, wp.element.createElement("label", {
    style: {
      display: 'block',
      marginBottom: '6px',
      fontWeight: 600
    }
  }, "Mode"), wp.element.createElement("select", {
    value: authorMode,
    onChange: function onChange(event) {
      return setAuthorMode(event.target.value);
    },
    style: {
      width: '100%',
      marginBottom: '12px'
    }
  }, wp.element.createElement("option", {
    value: "draft"
  }, "Draft"), wp.element.createElement("option", {
    value: "abstract"
  }, "Abstract"), wp.element.createElement("option", {
    value: "enrichment"
  }, "Enrichment")), wp.element.createElement(TextareaControl, {
    label: "Framework Brief ID",
    value: frameworkBriefId,
    onChange: function onChange(value) {
      return setFrameworkBriefId(value);
    },
    placeholder: "FG brief ID (required for draft)"
  }), wp.element.createElement(TextareaControl, {
    label: "Planner Session ID",
    value: plannerSessionId,
    onChange: function onChange(value) {
      return setPlannerSessionId(value);
    },
    placeholder: "Editorial Planner session ID (required for draft)"
  }), wp.element.createElement(TextareaControl, {
    label: "Author Instructions (optional)",
    value: authorInstructions,
    onChange: function onChange(value) {
      return setAuthorInstructions(value);
    },
    placeholder: "Optional constraints or notes"
  }), wp.element.createElement(PanelBody, {
    title: "Core Settings",
    initialOpen: false
  }, wp.element.createElement(TextareaControl, {
    label: "Industry Focus",
    value: authorCoreSettings.industry_focus,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        industry_focus: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Audience Tier",
    value: authorCoreSettings.audience_tier,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        audience_tier: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Risk Tolerance",
    value: authorCoreSettings.risk_tolerance,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        risk_tolerance: value
      }));
    }
  }), wp.element.createElement(TextareaControl, {
    label: "Brand Profile",
    value: authorCoreSettings.brand_profile,
    onChange: function onChange(value) {
      return setAuthorCoreSettings(_objectSpread(_objectSpread({}, authorCoreSettings), {}, {
        brand_profile: value
      }));
    }
  })), wp.element.createElement(Button, {
    isPrimary: true,
    onClick: handleAuthorSubmit,
    disabled: authorLoading
  }, authorLoading ? wp.element.createElement(Spinner, null) : 'Run Author Agent'), wp.element.createElement(Button, {
    isSecondary: true,
    onClick: insertBlocksFromAuthor,
    disabled: !authorBlocks.length || authorError,
    style: {
      marginLeft: '10px'
    }
  }, "Insert Blocks"), authorError && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#ffe6e6',
      border: '1px solid #ff9999',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Error:"), " ", authorError), authorWarnings.length > 0 && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#fff4e5',
      border: '1px solid #ffb74d',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Warnings:"), wp.element.createElement("ul", null, authorWarnings.map(function (warning, index) {
    return wp.element.createElement("li", {
      key: index
    }, warning);
  }))), authorValidationErrors.length > 0 && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#ffe6e6',
      border: '1px solid #ff9999',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Validation Errors:"), wp.element.createElement("ul", null, authorValidationErrors.map(function (error, index) {
    return wp.element.createElement("li", {
      key: index
    }, error);
  }))), authorResults && !authorError && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#e6ffe6',
      border: '1px solid #99ff99',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Author Results:"), wp.element.createElement("p", null, authorResults)), authorAbstract && wp.element.createElement("div", {
    style: {
      marginTop: '10px',
      padding: '10px',
      backgroundColor: '#f0f4ff',
      border: '1px solid #99b5ff',
      borderRadius: '4px'
    }
  }, wp.element.createElement("strong", null, "Abstract Output:"), wp.element.createElement("pre", {
    style: {
      whiteSpace: 'pre-wrap'
    }
  }, JSON.stringify(authorAbstract, null, 2)))));
};
registerPlugin('dual-gpt-sidebar', {
  render: DualGPTSidebar,
  icon: 'admin-tools'
});
