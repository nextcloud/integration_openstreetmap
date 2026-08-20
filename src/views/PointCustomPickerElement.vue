<template>
	<MapLocationPicker
		@submit="onSubmit" />
</template>

<script>
import MapLocationPicker from './MapLocationPicker.vue'

export default {
	name: 'PointCustomPickerElement',

	components: {
		MapLocationPicker,
	},

	props: {
		providerId: {
			type: String,
			required: true,
		},
		accessible: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onSubmit(data) {
			this.$el.dispatchEvent(new CustomEvent('submit', { detail: data.link, bubbles: true }))
		},
	},
}
</script>

<style lang="scss">
// TODO fix this in nc/vue
.modal-container__content .reference-picker-modal--content {
	height: 100%;
}
</style>

<style scoped lang="scss">
// nothing yet
</style>
