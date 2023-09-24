<template>
	<div class="map-wrapper">
		<a href="https://www.maptiler.com" class="watermark" :class="{ padded: showMousePositionControl }">
			<img src="https://api.maptiler.com/resources/logo.svg"
				alt="MapTiler logo">
		</a>
		<div
			ref="mapContainer"
			class="osm-integration-map" />
		<div v-if="map"
			class="map-content">
			<VMarker v-if="marker"
				:map="map"
				:lng-lat="marker" />
			<!-- some stuff go away when changing the style -->
			<div v-if="mapLoaded">
				<PolygonFill v-if="area"
					layer-id="target-object"
					:geojson="area"
					:map="map"
					:fill-opacity="0.25" />
			</div>
		</div>
	</div>
</template>

<script>
import maplibregl, {
	Map, NavigationControl, ScaleControl, GeolocateControl,
	FullscreenControl, TerrainControl,
} from 'maplibre-gl'
import MaplibreGeocoder from '@maplibre/maplibre-gl-geocoder'
import '@maplibre/maplibre-gl-geocoder/dist/maplibre-gl-geocoder.css'

import { loadState } from '@nextcloud/initial-state'
import {
	getRasterTileServers,
	getVectorStyles,
} from '../../tileServers.js'
import { MousePositionControl, TileControl } from '../../mapControls.js'
import { maplibreForwardGeocode } from '../../mapUtils.js'

import VMarker from './VMarker.vue'
import PolygonFill from './PolygonFill.vue'

const DEFAULT_MAP_MAX_ZOOM = 22

export default {
	name: 'MaplibreMap',

	components: {
		PolygonFill,
		VMarker,
	},

	props: {
		useTerrain: {
			type: Boolean,
			default: false,
		},
		marker: {
			type: Object,
			default: null,
		},
		bbox: {
			type: Object,
			default: null,
		},
		center: {
			type: Object,
			default: null,
		},
		zoom: {
			type: Number,
			default: null,
		},
		pitch: {
			type: Number,
			default: null,
		},
		bearing: {
			type: Number,
			default: null,
		},
		mapStyle: {
			type: String,
			default: 'streets',
		},
		area: {
			type: Object,
			default: null,
		},
		unit: {
			type: String,
			default: 'metric',
		},
		showMousePositionControl: {
			type: Boolean,
			default: false,
		},
		allMoveEvents: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			map: null,
			styles: {},
			mapLoaded: false,
			mousePositionControl: null,
			scaleControl: null,
			terrainControl: null,
			apiKeys: loadState('integration_openstreetmap', 'api-keys'),
		}
	},

	computed: {
	},

	watch: {
		showMousePositionControl(newValue) {
			if (newValue) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			} else {
				this.map.removeControl(this.mousePositionControl)
			}
		},
		unit(newValue) {
			this.scaleControl?.setUnit(newValue)
		},
		useTerrain(newValue) {
			if (newValue) {
				this.terrainControl._toggleTerrain()
			} else {
				this.map.setTerrain()
			}
		},
		pitch(newValue) {
			this.map.setPitch(newValue)
		},
		bearing(newValue) {
			this.map.setBearing(newValue)
		},
	},

	mounted() {
		this.initMap()
	},

	destroyed() {
		this.map.remove()
	},

	methods: {
		initMap() {
			const apiKey = this.apiKeys.maptiler_api_key
			// tile servers and styles
			this.styles = {
				...getVectorStyles(apiKey),
				...getRasterTileServers(apiKey),
			}
			const restoredStyleKey = Object.keys(this.styles).includes(this.mapStyle) ? this.mapStyle : 'streets'
			const restoredStyleObj = this.styles[restoredStyleKey]
			this.$emit('map-state-change', { mapStyle: restoredStyleKey })

			const mapOptions = {
				container: this.$refs.mapContainer,
				style: restoredStyleObj.uri ? restoredStyleObj.uri : restoredStyleObj,
				// center: centerLngLat,
				// zoom: this.settings.zoom ?? 1,
				// pitch: this.settings.pitch ?? 0,
				// bearing: this.settings.bearing ?? 0,
				// bounds,
				maxPitch: 75,
				maxZoom: restoredStyleObj.maxzoom ? (restoredStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM,
			}
			this.map = new Map(mapOptions)
			if (this.bbox) {
				const nsew = this.bbox
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 16,
					animate: false,
				})
			}
			if (this.zoom !== null) {
				this.map.setZoom(this.zoom)
			}
			if (this.center !== null) {
				this.map.setCenter(this.center)
			}
			if (this.pitch !== null) {
				this.map.setPitch(this.pitch)
			}
			if (this.bearing !== null) {
				this.map.setBearing(this.bearing)
			}
			const navigationControl = new NavigationControl({ visualizePitch: true })
			this.scaleControl = new ScaleControl({ unit: this.unit })

			this.map.addControl(
				new MaplibreGeocoder({ forwardGeocode: maplibreForwardGeocode }, {
					maplibregl,
					placeholder: t('integration_openstreetmap', 'Search on the map'),
					minLength: 4,
					debounceSearch: 400,
					popup: true,
					showResultsWhileTyping: true,
				}),
				'top-left',
			)

			const geolocateControl = new GeolocateControl({
				trackUserLocation: true,
				positionOptions: {
					enableHighAccuracy: true,
					timeout: 10000,
				},
			})
			const fullscreenControl = new FullscreenControl()
			this.map.addControl(navigationControl, 'bottom-right')
			this.map.addControl(this.scaleControl, 'top-left')
			this.map.addControl(geolocateControl, 'top-left')

			// mouse position
			this.mousePositionControl = new MousePositionControl()
			if (this.showMousePositionControl) {
				this.map.addControl(this.mousePositionControl, 'bottom-left')
			}

			// custom tile control
			const tileControl = new TileControl({ styles: this.styles, selectedKey: restoredStyleKey })
			tileControl.on('changeStyle', (key) => {
				this.$emit('map-state-change', { mapStyle: key })
				const mapStyleObj = this.styles[key]
				this.map.setMaxZoom(mapStyleObj.maxzoom ? (mapStyleObj.maxzoom - 0.01) : DEFAULT_MAP_MAX_ZOOM)

				// if we change the tile/style provider => redraw layers
				this.reRenderLayersAndTerrain()
			})
			this.map.addControl(tileControl, 'top-right')
			this.map.addControl(fullscreenControl, 'top-right')
			this.terrainControl = new TerrainControl({
				source: 'terrain',
				exaggeration: 2.5,
			})
			this.map.addControl(this.terrainControl, 'top-right')
			this.terrainControl._terrainButton.addEventListener('click', () => {
				this.$emit('map-state-change', { terrain: !!this.map.getTerrain() })
			})

			this.handleMapEvents()

			this.map.on('load', () => {
				this.mapLoaded = true
				this.addTerrainSource()
				if (this.useTerrain) {
					this.terrainControl._toggleTerrain()
				}
				setTimeout(() => {
					this.emitMapState()
					this.emitMapBounds()
				}, 300)
			})
		},
		reRenderLayersAndTerrain() {
			// re render the layers
			this.mapLoaded = false
			setTimeout(() => {
				this.$nextTick(() => {
					this.mapLoaded = true
				})
			}, 500)

			// add the terrain
			setTimeout(() => {
				this.$nextTick(() => {
					// terrain is not disabled anymore by maplibre when switching tile layers
					// it is still needed to add the source as it goes away when switching from a vector to a raster one
					this.addTerrainSource()
				})
			}, 500)
		},
		addTerrainSource() {
			const apiKey = this.apiKeys.maptiler_api_key
			if (!this.map.getSource('terrain')) {
				this.map.addSource('terrain', {
					type: 'raster-dem',
					url: 'https://api.maptiler.com/tiles/terrain-rgb/tiles.json?key=' + apiKey,
				})
			}
		},
		handleMapEvents() {
			this.map.on('moveend', () => {
				this.emitMapState()
				this.emitMapBounds()
			})
			if (this.allMoveEvents) {
				this.map.on('move', () => {
					this.emitMapState()
				})
			}
		},
		emitMapState() {
			const { lng, lat } = this.map.getCenter()
			this.$emit('map-state-change', {
				centerLng: lng,
				centerLat: lat,
				zoom: this.map.getZoom(),
				pitch: this.map.getPitch(),
				bearing: this.map.getBearing(),
			})
		},
		emitMapBounds() {
			const bounds = this.map.getBounds()
			this.$emit('map-bounds-change', {
				north: bounds.getNorth(),
				east: bounds.getEast(),
				south: bounds.getSouth(),
				west: bounds.getWest(),
			})
		},
		onZoomOn(nsew) {
			if (this.map) {
				this.map.fitBounds([[nsew.west, nsew.north], [nsew.east, nsew.south]], {
					padding: 50,
					maxZoom: 18,
				})
			}
		},
	},
}
</script>
<style lang="scss">
@import '../../../css/maplibre.scss';
</style>

<style scoped lang="scss">
@import '~maplibre-gl/dist/maplibre-gl.css';

.map-wrapper {
	position: relative;
	width: 100%;
	height: 100%;

	.osm-integration-map {
		width: 100%;
		height: 100%;
	}

	.watermark {
		position: absolute;
		z-index: 999;
		left: 10px;
		bottom: 0;
		&.padded {
			bottom: 18px;
		}
	}
}
</style>
