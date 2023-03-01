/**
 * @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
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

import {
	registerWidget,
	registerCustomPickerElement,
	NcCustomPickerRenderResult,
} from '@nextcloud/vue/dist/Components/NcRichText.js'

__webpack_nonce__ = btoa(OC.requestToken) // eslint-disable-line
__webpack_public_path__ = OC.linkTo('integration_openstreetmap', 'js/') // eslint-disable-line

registerWidget('integration_openstreetmap_location', async (el, { richObjectType, richObject, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-lazy" */'vue')
	Vue.mixin({ methods: { t, n } })
	const { loadState } = await import(/* webpackChunkName: "reference-lazy" */'@nextcloud/initial-state')
	const preferOsmFrame = loadState('integration_openstreetmap', 'prefer-osm-frame')
	let ReferenceWidgetComponent
	if (preferOsmFrame) {
		const { default: OsmFrameReferenceWidget } = await import(/* webpackChunkName: "reference-frame-lazy" */'./views/OsmFrameReferenceWidget.vue')
		ReferenceWidgetComponent = OsmFrameReferenceWidget
	} else {
		const { default: MaplibreReferenceWidget } = await import(/* webpackChunkName: "reference-maplibre-lazy" */'./views/MaplibreReferenceWidget.vue')
		ReferenceWidgetComponent = MaplibreReferenceWidget
	}

	const Widget = Vue.extend(ReferenceWidgetComponent)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('openstreetmap-point', async (el, { providerId, accessible }) => {
	const { default: Vue } = await import(/* webpackChunkName: "reference-picker-lazy" */'vue')
	const { default: PointCustomPickerElement } = await import(/* webpackChunkName: "reference-picker-lazy" */'./views/PointCustomPickerElement.vue')
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
