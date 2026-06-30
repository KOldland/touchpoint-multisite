const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
    ...defaultConfig,
    entry: {
        "editorial-planner": path.resolve(__dirname, "assets/js/editorial-planner.js"),
        "editorial-sessions": path.resolve(__dirname, "assets/js/editorial-sessions.js"),
        "editorial-new-session": path.resolve(__dirname, "assets/js/editorial-new-session.js"),
        "editorial-top-line-categories": path.resolve(__dirname, "assets/js/editorial-top-line-categories.js"),
        "editorial-content-gaps": path.resolve(__dirname, "assets/js/editorial-content-gaps.js"),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, "assets/js/build"),
        filename: "[name].js",
    },
};
