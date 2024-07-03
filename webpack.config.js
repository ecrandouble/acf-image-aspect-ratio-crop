const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const path = require('path');

module.exports = (env, argv) => ({
  entry: {
    'input-script': './assets/src/index.js',
    'input-style': './assets/src/input.scss',
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'assets/dist'),
  },
  resolve: {
    extensions: ['*', '.js'],
  },
  performance: {
    hints: false,
  },
  devtool: 'source-map',
  mode: 'development',
  module: {
    rules: [
      {
        test: /\.js$/,
        use: [
          {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env'],
            },
          },
        ],
      },
      {
        test: /\.(png|jpg|jpeg|tiff|webp|gif|ico|woff|woff2|eot|ttf|otf|mp4|webm|wav|mp3|m4a|aac|oga)$/,
        type: 'asset/resource',
      },
      {
        test: /\.svg$/,
        type: 'asset/source',
      },
      {
        test: /\.s?css$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              sourceMap: true,
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: [['autoprefixer', {}]],
              },
              sourceMap: true,
            },
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true,
            },
          },
        ],
      },
    ],
  },
  plugins: [
    new MiniCssExtractPlugin({
      filename: '[name].css',
      chunkFilename: '[id].css',
    }),
  ],
});
