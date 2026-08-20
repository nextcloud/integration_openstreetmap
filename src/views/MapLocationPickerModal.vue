<template>
	<NcModal size="large"
		:name="t('integration_openstreetmap', 'Pick pick')"
		@close="onClose">
		<MapLocationPicker
			class="location-picker"
			:show-link-options="false"
			:submit-label="t('integration_openstreetmap', 'Pick this location')"
			:search-placeholder="t('integration_openstreetmap', 'Search with Nominatim to pick a location')"
			:picker-title="t('integration_openstreetmap', 'Pick a location')"
			@submit="onSubmit" />
	</NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/components/NcModal'

import MapLocationPicker from './MapLocationPicker.vue'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'MapLocationPickerModal',

	components: {
		MapLocationPicker,
		NcModal,
	},

	props: {
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
			axios.get(generateOcsUrl('references/resolve') + `?reference=${encodeURIComponent(data.link)}`)
				.then((response) => {
					const references = Object.values(response.data.ocs.data.references)
					const resultData = {
						...data,
						location: references[0]?.richObject,
					}
					this.$el.dispatchEvent(new CustomEvent('submit', { detail: resultData, bubbles: true }))
				})
				.catch((error) => {
					console.error('Failed to extract references', error)
					this.$el.dispatchEvent(new CustomEvent('close', { bubbles: true }))
				})
		},
		onClose() {
			this.$el.dispatchEvent(new CustomEvent('close', { bubbles: true }))
		},
	},
}
</script>

<style scoped lang="scss">
.location-picker {
	height: 100%;
}
</style>
