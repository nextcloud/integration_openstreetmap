/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/components/NcRichText'

registerWidget('integration_openstreetmap_route', async (el, { richObjectType, richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { default: MaplibreRouteReferenceWidget } = await import('./views/MaplibreRouteReferenceWidget.vue')

	const app = createApp(
		MaplibreRouteReferenceWidget,
		{
			richObjectType,
			richObject,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)
}, () => {}, { hasInteractiveView: false })

registerWidget('integration_openstreetmap_location', async (el, { richObjectType, richObject, accessible }) => {
	const { createApp } = await import('vue')
	const { loadState } = await import('@nextcloud/initial-state')
	const preferOsmFrame = loadState('integration_openstreetmap', 'prefer-osm-frame')
	let ReferenceWidgetComponent
	if (preferOsmFrame) {
		const { default: OsmFrameReferenceWidget } = await import('./views/OsmFrameReferenceWidget.vue')
		ReferenceWidgetComponent = OsmFrameReferenceWidget
	} else {
		const { default: MaplibreLocationReferenceWidget } = await import('./views/MaplibreLocationReferenceWidget.vue')
		ReferenceWidgetComponent = MaplibreLocationReferenceWidget
	}

	const app = createApp(
		ReferenceWidgetComponent,
		{
			richObjectType,
			richObject,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)
}, () => {}, { hasInteractiveView: false })

registerCustomPickerElement('openstreetmap-point', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: PointCustomPickerElement } = await import('./views/PointCustomPickerElement.vue')

	const app = createApp(
		PointCustomPickerElement,
		{
			providerId,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)

	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
})

registerCustomPickerElement('openstreetmap-direction', async (el, { providerId, accessible }) => {
	const { createApp } = await import('vue')
	const { default: DirectionCustomPickerElement } = await import('./views/DirectionCustomPickerElement.vue')

	const app = createApp(
		DirectionCustomPickerElement,
		{
			providerId,
			accessible,
		},
	)
	app.mixin({ methods: { t, n } })
	app.mount(el)

	return new NcCustomPickerRenderResult(el, app)
}, (el, renderResult) => {
	renderResult.object.unmount()
})
