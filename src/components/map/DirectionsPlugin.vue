<script>
import MapLibreGlDirections from '@maplibre/maplibre-gl-directions'

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
			this.directions = new MapLibreGlDirections(this.map, {
				api: generateUrl('/apps/integration_openstreetmap/osrm/route'),
				profile: this.profile,
				requestOptions: {
					alternatives: 'true',
				},
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
	},
	render(h) {
		return null
	},
}
</script>
