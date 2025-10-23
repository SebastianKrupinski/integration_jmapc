/**
 * SPDX-FileCopyrightText: Sebastian Krupinski <krupinski01@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
	plugins: [vue()],
	build: {
		outDir: 'js',
		emptyOutDir: false,
		cssCodeSplit: true,
		rollupOptions: {
			input: {
				UserSettings: resolve(__dirname, 'src/UserSettings.ts'),
				AdminSettings: resolve(__dirname, 'src/AdminSettings.ts'),
			},
			output: {
				entryFileNames: '[name].js',
				chunkFileNames: '[name]-[hash].js',
				assetFileNames: (assetInfo) => {
					if (assetInfo.name?.endsWith('.css')) {
						return '[name].css'
					}
					return '[name].[ext]'
				},
			},
		},
	},
	resolve: {
		alias: {
			'@': resolve(__dirname, 'src'),
		},
	},
})
