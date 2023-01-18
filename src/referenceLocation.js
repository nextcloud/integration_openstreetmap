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

import { loadState } from '@nextcloud/initial-state'
import {
	registerWidget,
	registerCustomPickerElement,
	CustomPickerRenderResult,
} from '@nextcloud/vue-richtext'
import './bootstrap.js'
import Vue from 'vue'
import OsmFrameReferenceWidget from './views/OsmFrameReferenceWidget.vue'
import MaplibreReferenceWidget from './views/MaplibreReferenceWidget.vue'
import PointCustomPickerElement from './views/PointCustomPickerElement.vue'

const preferOsmFrame = loadState('integration_openstreetmap', 'prefer-osm-frame')
const referenceWidget = preferOsmFrame ? OsmFrameReferenceWidget : MaplibreReferenceWidget

registerWidget('integration_openstreetmap_location', (el, { richObjectType, richObject, accessible }) => {
	const Widget = Vue.extend(referenceWidget)
	new Widget({
		propsData: {
			richObjectType,
			richObject,
			accessible,
		},
	}).$mount(el)
})

registerCustomPickerElement('openstreetmap-point', (el, { providerId, accessible }) => {
	const Element = Vue.extend(PointCustomPickerElement)
	const vueElement = new Element({
		propsData: {
			providerId,
			accessible,
		},
	}).$mount(el)
	return new CustomPickerRenderResult(vueElement.$el, vueElement)
}, (el, renderResult) => {
	console.debug('osm custom destroy callback. el', el, 'renderResult:', renderResult)
	renderResult.object.$destroy()
})
