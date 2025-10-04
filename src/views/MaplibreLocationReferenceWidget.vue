<!--
  - @copyright Copyright (c) 2023 Julien Veyssier <julien-nc@posteo.net>
  -
  - @author 2023 Julien Veyssier <julien-nc@posteo.net>
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
			<a v-if="richObject.display_name"
				class="location--link"
				:href="richObject.url"
				target="_blank">
				<strong class="location--name">
					{{ richObject.display_name }}
				</strong>
			</a>
			<MaplibreMap
				class="location--map"
				scrolling="no"
				:bbox="bbox"
				:center="mapCenter"
				:zoom="zoom"
				:pitch="pitch"
				:bearing="bearing"
				:map-style="style"
				:use-terrain="useTerrain">
				<template #default="{ map }">
					<VMarker v-if="markerCoords"
						:map="map"
						:lng-lat="markerCoords" />
					<PolygonFill v-if="richObject.geojson"
						layer-id="target-object"
						:geojson="richObject.geojson"
						:map="map"
						:fill-opacity="0.25" />
				</template>
			</MaplibreMap>
		</div>
	</div>
</template>

<script>
import MaplibreMap from '../components/map/MaplibreMap.vue'
import VMarker from '../components/map/VMarker.vue'
import PolygonFill from '../components/map/PolygonFill.vue'

export default {
	name: 'MaplibreLocationReferenceWidget',

	components: {
		PolygonFill,
		VMarker,
		MaplibreMap,
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
		bbox() {
			// use mapCenter in priority
			if (this.mapCenter) {
				return undefined
			}
			const bb = this.richObject.boundingbox
			return {
				north: bb[1],
				south: bb[0],
				east: bb[3],
				west: bb[2],
			}
		},
		zoom() {
			return this.richObject.zoom
		},
		pitch() {
			return this.richObject.pitch
		},
		bearing() {
			return this.richObject.bearing
		},
		style() {
			return this.richObject.style ?? undefined
		},
		useTerrain() {
			return this.richObject.terrain ?? undefined
		},
		mapCenter() {
			return this.richObject.map_center
		},
		markerCoords() {
			return this.richObject.marker_coordinates
		},
	},

	methods: {
	},
}
</script>

<style scoped lang="scss">
.location-reference {
	width: 100%;
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

		&--map {
			width: 100%;
			height: 350px;
		}
	}
}
</style>
