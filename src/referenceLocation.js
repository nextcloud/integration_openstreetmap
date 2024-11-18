/**
 * @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
 *
 * @author Julien Veyssier <julien-nc@posteo.net>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { registerWidget, registerCustomPickerElement, NcCustomPickerRenderResult } from '@nextcloud/vue/dist/Components/NcRichText.js'

registerWidget('integration_openstreetmap_route', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
	const { default: MaplibreRouteReferenceWidget } = await import('./views/MaplibreRouteReferenceWidget.vue')
	const ReferenceWidgetComponent = MaplibreRouteReferenceWidget

	const Widget = Vue.extend(ReferenceWidgetComponent)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
}, () => {}, { hasInteractiveView: false })

registerWidget('integration_openstreetmap_location', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import('vue')
	Vue.mixin({ methods: { t, n } })
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

	const Widget = Vue.extend(ReferenceWidgetComponent)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
}, () => {}, { hasInteractiveView: false })

registerCustomPickerElement('openstreetmap-point', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	const { default: PointCustomPickerElement } = await import('./views/PointCustomPickerElement.vue')
	Vue.mixin({ methods: { t, n } })

	const Element = Vue.extend(PointCustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})

registerCustomPickerElement('openstreetmap-direction', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import('vue')
	const { default: DirectionCustomPickerElement } = await import('./views/DirectionCustomPickerElement.vue')
	Vue.mixin({ methods: { t, n } })

	const Element = Vue.extend(DirectionCustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new NcCustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	renderResult.object.$destroy()
})
