<script>
import { Marker } from 'maplibre-gl'

export default {
	name: 'VMarker',

	components: {
	},

	props: {
		lngLat: {
			type: [Object, Array],
			required: true,
		},
		color: {
			type: String,
			default: undefined,
		},
		map: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			ready: false,
			mapObject: null,
		}
	},

	computed: {
	},

	watch: {
		lngLat(newValue) {
			this.mapObject.setLngLat(newValue)
		},
	},

	mounted() {
		this.init()
	},

	destroyed() {
		this.mapObject.remove()
	},

	methods: {
		init() {
			this.mapObject = new Marker({ color: this.color })
				.setLngLat(this.lngLat)
				.addTo(this.map)
			this.ready = true
		},
	},
	render(h) {
		return null
	},
}
</script>
