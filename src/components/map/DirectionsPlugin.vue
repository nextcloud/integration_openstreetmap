<script>
import MapLibreGlDirections, { layersFactory } from '@maplibre/maplibre-gl-directions'

import { generateUrl } from '@nextcloud/router'

export default {
	name: 'DirectionsPlugin',

	components: {
	},

	props: {
		map: {
			type: Object,
			required: true,
		},
		profile: {
			type: String,
			default: 'routed-car',
		},
	},

	data() {
		return {
			ready: false,
			directions: null,
			layers: null,
			waypointsBackup: [],
		}
	},

	computed: {
	},

	watch: {
		profile() {
			if (this.directions) {
				this.directions.destroy()
				this.init()
				this.directions.setWaypoints(this.waypointsBackup)
			}
		},
	},

	mounted() {
		console.debug('[osm] directions plugin map', this.map)
		this.init()
	},

	destroyed() {
		if (this.directions) {
			this.directions.destroy()
		}
	},

	methods: {
		init() {
			if (this.layers === null) {
				this.initLayers()
			}
			this.directions = new MapLibreGlDirections(this.map, {
				api: generateUrl('/apps/integration_openstreetmap/osrm/route'),
				profile: this.profile,
				requestOptions: {
					alternatives: 'true',
				},
				layers: this.layers,
			})
			this.directions.hoverable = true
			this.directions.interactive = true
			this.directions.allowRouteSwitch = true
			this.directions.on('addwaypoint', (event) => {
				this.updateWaypoints()
			})
			this.directions.on('movewaypoint', (event) => {
				this.updateWaypoints()
			})
			this.directions.on('removewaypoint', (event) => {
				this.updateWaypoints()
			})
			this.directions.on('fetchroutesend', (event) => {
				console.debug('[osm] fetch route', event.data)
				this.$emit('route-fetch', event.data.routes)
			})
			console.debug('[osm] directions init', this.directions)
		},
		updateWaypoints() {
			this.$emit('waypoint-change', this.directions.waypoints)
			this.waypointsBackup = this.directions.waypoints
		},
		initLayers() {
			this.layers = layersFactory()

			const wpLayerIndex = this.layers.findIndex(l => l.id === 'maplibre-gl-directions-waypoint')
			this.layers.splice(wpLayerIndex, 1)
			const wpCasingLayerIndex = this.layers.findIndex(l => l.id === 'maplibre-gl-directions-waypoint-casing')
			this.layers.splice(wpCasingLayerIndex, 1)

			// make the casing invisible
			this.layers.push({
				id: 'maplibre-gl-directions-waypoint-casing',
				type: 'circle',
				source: 'maplibre-gl-directions',
				paint: {
					'circle-radius': 0,
					'circle-opacity': 0,
				},
				filter: [
					'all',
					['==', ['geometry-type'], 'Point'],
					['==', ['get', 'type'], 'WAYPOINT'],
				],
			})
			// replace the waypoints
			this.layers.push({
				id: 'maplibre-gl-directions-waypoint',
				type: 'symbol',
				source: 'maplibre-gl-directions',
				layout: {
					'icon-image': [
						'case',
						['==', ['get', 'category'], 'ORIGIN'],
						'marker_green',
						['==', ['get', 'category'], 'DESTINATION'],
						'marker_red',
						'marker',
					],
					'icon-anchor': 'bottom',
					'icon-size': 1,
					'icon-offset': [0, 6],
					'icon-allow-overlap': true,
				},
				paint: {
				},
				filter: [
					'all',
					['==', ['geometry-type'], 'Point'],
					['==', ['get', 'type'], 'WAYPOINT'],
					// ['in', ['get', 'category'], ['literal', ['ORIGIN', 'DESTINATION']]],
				],
			})
		},
	},
	render(h) {
		return null
	},
}
</script>
