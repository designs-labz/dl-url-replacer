const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = {
    entry: './admin/assets/js/script.js',
    output: {
        filename: 'script.bundle.js',
        path: path.resolve(__dirname, 'admin/assets/js'),
    },
    module: {
        rules: [{
            test: /\.scss$/,
            use: [
                MiniCssExtractPlugin.loader,
                'css-loader',
                {
                    loader: 'sass-loader',
                    options: {
                        implementation: require('sass'),
                    }
                }
            ]
        }]
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: '../css/style.css'
        })
    ],
    mode: 'development'
};
