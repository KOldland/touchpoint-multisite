const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
    ...defaultConfig,
    entry: {
        index: path.resolve( __dirname, 'src/index.js' ),
        'suggest-plugin': path.resolve( __dirname, 'src/suggest-plugin.js' ),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve( __dirname, 'build' ),
    },
};
