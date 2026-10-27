module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './storage/framework/views/*.php',
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        'homiso-green': '#77BC23',
        'homiso-gray': '#7A7A7A',
        'homiso-lightgray': '#F3F3F3',
        'homiso-orange': '#F4A12C',
      },
    },
  },
  plugins: [],
};
