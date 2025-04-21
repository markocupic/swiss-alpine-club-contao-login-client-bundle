const Encore = require('@symfony/webpack-encore');

Encore
	.setOutputPath('public/')
	.setPublicPath('/bundles/markocupicswissalpineclubcontaologinclient')
	.setManifestKeyPrefix('')

    //.addEntry('backend', './assets/backend.js')
    //.addEntry('frontend', './assets/frontend.js')

	.copyFiles({
		from: './assets/js',
		to: 'js/[path][name].[hash:8].[ext]',
	})
	.copyFiles({
		from: './assets/img',
		to: 'img/[path][name].[ext]',
	})
    // Preprocessing scss in css
    .enableSassLoader()
    .enablePostCssLoader()
    .addStyleEntry('styles/backend', './assets/styles/backend.scss')
    .addStyleEntry('styles/frontend', './assets/styles/frontend.scss')
    .addStyleEntry('styles/sac_login_button', './assets/styles/sac_login_button.scss')

	.disableSingleRuntimeChunk()
	.cleanupOutputBeforeBuild()
	.enableSourceMaps()
	.enableVersioning()

	// enables @babel/preset-env polyfills
	.configureBabelPresetEnv((config) => {
		config.useBuiltIns = 'usage';
		config.corejs = 3;
	})

;

module.exports = Encore.getWebpackConfig();
