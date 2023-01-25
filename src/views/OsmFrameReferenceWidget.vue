<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <eneiluj@posteo.net>
  -
  - @author 2023 Julien Veyssier <eneiluj@posteo.net>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -->

<template>
	<div class="location-reference">
		<div class="location">
			<a class="location--link"
				:href="richObject.url"
				target="_blank">
				<strong class="location--name">
					{{ richObject.display_name }}
				</strong>
			</a>
			<iframe
				class="location--map-frame"
				frameborder="0"
				scrolling="no"
				marginheight="0"
				marginwidth="0"
				:src="frameUrl" />
		</div>
	</div>
</template>

<script>
import { getBBFromCenterZoom } from '../mapUtils.js'

export default {
	name: 'OsmFrameReferenceWidget',

	components: {
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
		}
	},

	computed: {
		frameUrl() {
			const center = this.richObject.map_center
			const zoom = this.richObject.zoom
			const marker = this.richObject.marker_coordinates
			const markerLatLon = marker
				? marker.lat + ',' + marker.lon
				: null

			// <iframe width="425" height="350" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://www.openstreetmap.org/export/embed.html?bbox=4.5333194732666025%2C44.70715721664363%2C4.613656997680665%2C44.74807432587679&amp;layer=mapnik&amp;marker=44.72761938925528%2C4.573488235473633" style="border: 1px solid black"></iframe><br/><small><a href="https://www.openstreetmap.org/?mlat=44.7276&amp;mlon=4.5735#map=14/44.7276/4.5735">Afficher une carte plus grande</a></small>
			// https://www.openstreetmap.org/export/embed.html?bbox=4.5333194732666025%2C44.70715721664363%2C4.613656997680665%2C44.74807432587679&amp;layer=mapnik
			const bb = center
				? getBBFromCenterZoom(center.lat, center.lon, zoom)
				: this.richObject.boundingbox

			const bbp = [bb[2], bb[0], bb[3], bb[1]].join(',')
			return 'https://www.openstreetmap.org/export/embed.html?'
				+ 'bbox=' + encodeURIComponent(bbp)
				+ (markerLatLon ? ('&marker=' + encodeURIComponent(markerLatLon)) : '')
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.location-reference {
	width: 100%;
	// padding: 12px;
	white-space: normal;

	.location {
		width: 100%;
		display: flex;
		flex-direction: column;
		// in case there is an absolute inside
		position: relative;

		&--link {
			padding: 10px;
			&:hover {
				color: #58a6ff !important;
			}
		}

		&--map-frame {
			width: 100%;
			height: 350px;
		}
	}
}
</style>
