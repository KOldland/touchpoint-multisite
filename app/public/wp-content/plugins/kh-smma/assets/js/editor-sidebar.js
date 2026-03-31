(function () {
  "use strict";

  if (typeof window.wp === "undefined" || !window.wp.plugins || !window.wp.components || !window.wp.element || !window.wp.i18n) {
    return;
  }

  var registerPlugin = window.wp.plugins.registerPlugin;
  var PluginSidebar = (window.wp.editPost && window.wp.editPost.PluginSidebar) || (window.wp.editor && window.wp.editor.PluginSidebar);
  var PluginSidebarMoreMenuItem =
    (window.wp.editPost && window.wp.editPost.PluginSidebarMoreMenuItem) ||
    (window.wp.editor && window.wp.editor.PluginSidebarMoreMenuItem);
  var PanelBody = window.wp.components.PanelBody;
  var Button = window.wp.components.Button;
  var Notice = window.wp.components.Notice;
  var Text = window.wp.components.__experimentalText || window.wp.components.Text;
  var createElement = window.wp.element.createElement;
  var __ = window.wp.i18n.__;
  var SIDEBAR_NAME = "kh-smma-sidebar";
  var SIDEBAR_TITLE = __("Social Campaigns", "kh-smma");

  if (!registerPlugin || !PluginSidebar) {
    return;
  }

  function openCampaignWorkflow() {
    if (window.KHSMMAEditor && typeof window.KHSMMAEditor.openGenerateModal === "function") {
      window.KHSMMAEditor.openGenerateModal();
      return;
    }

    document.dispatchEvent(new CustomEvent("khSmmaOpenWorkflow"));
  }

  function SidebarContent() {
    return createElement(
      PanelBody,
      { title: __("Campaign Workflow", "kh-smma"), initialOpen: true },
      createElement(
        Text,
        null,
        __("Generate, edit, and schedule campaign variants from this post.", "kh-smma")
      ),
      createElement(
        "div",
        { style: { marginTop: "12px" } },
        createElement(
          Button,
          { variant: "primary", onClick: openCampaignWorkflow },
          __("Open Campaign Modal", "kh-smma")
        )
      ),
      createElement(
        "div",
        { style: { marginTop: "10px" } },
        createElement(
          Notice,
          { status: "info", isDismissible: false },
          __("Scheduling options follow the channel controls set in KH Social admin.", "kh-smma")
        )
      )
    );
  }

  registerPlugin("kh-smma-sidebar-panel", {
    render: function () {
      return createElement(
        PluginSidebar,
        { name: SIDEBAR_NAME, title: SIDEBAR_TITLE, icon: "share" },
        createElement(SidebarContent)
      );
    }
  });

  if (PluginSidebarMoreMenuItem) {
    registerPlugin("kh-smma-sidebar-menu", {
      render: function () {
        return createElement(
          PluginSidebarMoreMenuItem,
          { target: SIDEBAR_NAME, onClick: openCampaignWorkflow },
          SIDEBAR_TITLE
        );
      }
    });
  }
})();
