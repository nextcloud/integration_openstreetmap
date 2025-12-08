<template>
	<div id="osm_prefs" class="section">
		<h2>
			<OsmIcon class="icon" />
			{{ t('integration_openstreetmap', 'OpenStreetMap integration') }}
		</h2>
		<div id="osm-content">
			<NcNoteCard v-if="state.search_location_enabled && state.admin_search_location_enabled"
				type="info">
				{{ t('integration_openstreetmap', 'Warning, everything you type in the Unified Search menu will be sent to OpenStreetMap\'s Nominatim service.') }}
			</NcNoteCard>
			<NcNoteCard v-if="state.admin_search_location_enabled === false"
				type="info">
				{{ t('integration_tmdb', 'An administrator has disabled the OpenStreetMap Unified Search provider') }}
			</NcNoteCard>
			<NcFormBox>
				<NcFormBoxSwitch
					:model-value="state.search_location_enabled && state.admin_search_location_enabled"
					:disabled="!state.admin_search_location_enabled"
					@update:model-value="onCheckboxChanged($event, 'search_location_enabled')">
					{{ t('integration_openstreetmap', 'Enable searching for locations') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch
					:model-value="state.link_preview_enabled"
					@update:model-value="onCheckboxChanged($event, 'link_preview_enabled')">
					{{ t('integration_openstreetmap', 'Enable OpenStreetMap link previews') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch v-if="state.link_preview_enabled"
					:model-value="state.prefer_simple_osm_iframe"
					@update:model-value="onCheckboxChanged($event, 'prefer_simple_osm_iframe')">
					{{ t('integration_openstreetmap', 'Prefer simple OpenStreetMap frame') }}
				</NcFormBoxSwitch>
				<NcFormBoxSwitch
					:model-value="state.navigation_enabled"
					@update:model-value="onCheckboxChanged($event, 'navigation_enabled')">
					{{ t('integration_openstreetmap', 'Enable navigation link') }}
				</NcFormBoxSwitch>
			</NcFormBox>
		</div>
	</div>
</template>

<script>
import OsmIcon from './icons/OsmIcon.vue'

import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcFormBox from '@nextcloud/vue/components/NcFormBox'
import NcFormBoxSwitch from '@nextcloud/vue/components/NcFormBoxSwitch'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

export default {
	name: 'PersonalSettings',

	components: {
		OsmIcon,
		NcNoteCard,
		NcFormBox,
		NcFormBoxSwitch,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_openstreetmap', 'user-config'),
			loading: false,
		}
	},

	computed: {
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_openstreetmap/config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_openstreetmap', 'OpenStreetMap options saved'))
			}).catch((error) => {
				showError(
					t('integration_openstreetmap', 'Failed to save OpenStreetMap options')
					+ ': ' + (error.response?.data?.error ?? ''),
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#osm_prefs {
	#osm-content {
		margin: 16px 0 0 40px;
		display: flex;
		flex-direction: column;
		gap: 12px;
		max-width: 800px;
	}
	h2 {
		display: flex;
		align-items: center;
		justify-content: start;
		.icon {
			margin-right: 8px;
		}
	}
}
</style>
