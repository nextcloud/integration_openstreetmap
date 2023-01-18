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
			<MaplibreMap
				class="location--map"
				scrolling="no"
				:marker="markerCoords"
				:bbox="bbox"
				:area="richObject.geojson" />
		</div>
	</div>
</template>

<script>
import MaplibreMap from '../components/map/MaplibreMap.vue'

export default {
	name: 'MaplibreReferenceWidget',

	components: {
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
			const bb = this.richObject.boundingbox
			return {
				north: bb[1],
				south: bb[0],
				east: bb[3],
				west: bb[2],
			}
		},
		markerCoords() {
			return this.richObject.url_coordinates
				? {
					lat: this.richObject.url_coordinates.lat,
					lon: this.richObject.url_coordinates.lon,
				}
				: {
					lat: this.richObject.lat,
					lon: this.richObject.lon,
				}
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
