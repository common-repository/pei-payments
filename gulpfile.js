var gulp = require('gulp');
var wpPot = require('gulp-wp-pot');
 
gulp.task('default', function () {
    return gulp.src([
    	'*.php',
    	'**/*.php',
    	'**/**/*.php'
    	])
        .pipe(wpPot( {
            domain: 'pei-payment-gateway',
            package: 'WC Pei Payment Gateway'
        } ))
        .pipe(gulp.dest('languages/pei-payment-gateway.pot'));
});