/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createAppConfig } from '@nextcloud/vite-config'

const isProduction = process.env.NODE_ENV === 'production'

export default createAppConfig({
	personalSettings: 'src/personalSettings.js',
	adminSettings: 'src/adminSettings.js',
	referenceLocation: 'src/referenceLocation.js',
}, {
	config: {
		css: {
			modules: {
				localsConvention: 'camelCase',
			},
			preprocessorOptions: {
				scss: {
					api: 'modern-compiler',
				},
			},
		},
		build: {
			cssCodeSplit: true,
			rollupOptions: {
				treeshake: {
					// Remove unused module exports
					moduleSideEffects: false,
					// Optimize property access
					propertyReadSideEffects: false,
				}
			},
		},
		worker: {
			rollupOptions: {
				output: {
					entryFileNames: 'js/[name]-[hash].js',
				},
			},
		},
	},
	inlineCSS: { relativeCSSInjection: true },
	minify: isProduction,
})
