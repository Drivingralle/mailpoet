const babelParser = require('@babel/eslint-parser');
const FlatCompat = require('@eslint/eslintrc').FlatCompat;
const airbnbLegacyConfig = require('eslint-config-airbnb/legacy');
const prettierConfig = require('eslint-config-prettier');
const importPlugin = require('eslint-plugin-import');
const noOnlyTestsPlugin = require('eslint-plugin-no-only-tests');
const globals = require('globals');

// compat configs
const compat = new FlatCompat({ baseDirectory: __dirname });
const airbnbLegacyCompatConfig = compat.config(airbnbLegacyConfig);
const prettierCompatConfig = compat.config(prettierConfig);

module.exports = [
  ...airbnbLegacyCompatConfig,
  ...prettierCompatConfig,
  {
    languageOptions: {
      parser: babelParser,
      parserOptions: {
        ecmaVersion: 6,
        sourceType: 'module',
      },
      globals: {
        ...globals.browser,
      },
    },
    plugins: {
      import: importPlugin,
      'no-only-tests': noOnlyTestsPlugin,
    },
    rules: {
      'import/prefer-default-export': 0, // we want to stop using default exports and start using named exports
      'no-underscore-dangle': 0, // Backbone uses underscores, we cannot remove them
      'import/no-default-export': 1, // no default exports
      'no-only-tests/no-only-tests': 2,
    },
  },
];
