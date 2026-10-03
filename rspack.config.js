/**
 * SPDX-FileCopyrightText: 2026 Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import browserslistConfig from '@nextcloud/browserslist-config'
import { defineConfig } from '@rspack/cli'
import { CssExtractRspackPlugin, DefinePlugin, LightningCssMinimizerRspackPlugin, ProgressPlugin, SwcJsMinimizerRspackPlugin } from '@rspack/core'
import NodePolyfillPlugin from '@rspack/plugin-node-polyfill'
import browserslist from 'browserslist'
import path from 'node:path'
import { VueLoaderPlugin } from 'vue-loader'

// browserslist-rs does not support baseline queries yet, so resolve the minimal browser versions manually
// See: https://github.com/browserslist/browserslist-rs/issues/40
const minBrowserVersion = browserslist(browserslistConfig)
	.map((str) => str.split(' '))
	.reduce((minVersion, [browser, version]) => {
		minVersion[browser] = minVersion[browser] ? Math.min(minVersion[browser], parseFloat(version)) : parseFloat(version)
		return minVersion
	}, {})
const targets = Object.entries(minBrowserVersion).map(([browser, version]) => `${browser} >=${version}`).join(',')

export default defineConfig((env) => {
	const appName = process.env.npm_package_name
	const appVersion = process.env.npm_package_version

	const mode = (env.development && 'development') || (env.production && 'production') || process.env.NODE_ENV || 'production'
	const isDev = mode === 'development'
	process.env.NODE_ENV = mode

	console.info('Building', appName, appVersion, '\n')

	return {
		target: 'web',
		mode,
		devtool: isDev ? 'cheap-source-map' : 'source-map',
		stats: 'normal',

		entry: {
			UserSettings: path.join(import.meta.dirname, 'src', 'UserSettings.ts'),
			AdminSettings: path.join(import.meta.dirname, 'src', 'AdminSettings.ts'),
		},

		output: {
			path: path.resolve(import.meta.dirname, 'js'),
			filename: `${appName}-[name].js?v=[contenthash]`,
			chunkFilename: `${appName}-[name].js?v=[contenthash]`,
			publicPath: 'auto',
			assetModuleFilename: '[name].[ext]?v=[contenthash]',
			clean: true,
			devtoolNamespace: appName,
			devtoolModuleFilenameTemplate(info) {
				const rel = path.relative(import.meta.dirname, info.absoluteResourcePath)
				return `webpack:///${appName}/${rel}`
			},
		},

		optimization: {
			chunkIds: 'named',
			splitChunks: {
				automaticNameDelimiter: '-',
				cacheGroups: {
					defaultVendors: {
						reuseExistingChunk: true,
					},
				},
			},
			minimize: !isDev,
			minimizer: [
				new SwcJsMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
				new LightningCssMinimizerRspackPlugin({
					minimizerOptions: {
						targets,
					},
				}),
			],
		},

		module: {
			rules: [
				{
					test: /\.vue$/,
					loader: 'vue-loader',
					options: {
						experimentalInlineMatchResource: true,
					},
				},
				{
					test: /\.css$/,
					use: [
						CssExtractRspackPlugin.loader,
						'css-loader',
					],
				},
				{
					test: /\.scss$/,
					use: [
						CssExtractRspackPlugin.loader,
						'css-loader',
						{
							loader: 'sass-loader',
							options: {
								sassOptions: {
									// prevents a stray BOM between merged stylesheets
									// See: https://github.com/nextcloud-libraries/webpack-vue-config/pull/798
									charset: false,
								},
							},
						},
					],
				},
				{
					test: /\.[cm]?js$/,
					exclude: /node_modules/,
					loader: 'builtin:swc-loader',
					options: {
						jsc: {
							parser: {
								syntax: 'ecmascript',
							},
						},
						env: {
							targets,
						},
					},
					type: 'javascript/auto',
				},
				{
					test: /\.ts$/,
					exclude: /node_modules/,
					loader: 'builtin:swc-loader',
					options: {
						jsc: {
							parser: {
								syntax: 'typescript',
							},
						},
						env: {
							targets,
						},
					},
					type: 'javascript/auto',
				},
				{
					test: /\.(png|jpe?g|gif|svg|webp)$/i,
					type: 'asset',
				},
				{
					test: /\.(woff2?|eot|ttf|otf)$/i,
					type: 'asset/resource',
				},
			],
		},

		plugins: [
			new ProgressPlugin(),
			new VueLoaderPlugin(),
			new NodePolyfillPlugin(),
			new DefinePlugin({
				appName: JSON.stringify(appName),
				appVersion: JSON.stringify(appVersion),
				// Vue compile time flags, see https://vuejs.org/api/compile-time-flags.html
				__VUE_OPTIONS_API__: true,
				__VUE_PROD_DEVTOOLS__: false,
				__VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
			}),
			new CssExtractRspackPlugin({
				filename: `../css/${appName}-[name].css`,
				chunkFilename: '../css/[id].chunk.css',
				ignoreOrder: true,
			}),
		],

		resolve: {
			extensions: ['*', '.ts', '.js', '.vue', '.json'],
			symlinks: false,
			fallback: {
				fs: false,
			},
		},

		cache: true,
	}
})
