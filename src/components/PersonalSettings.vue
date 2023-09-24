<template>
	<div id="osm_prefs" class="section">
		<h2>
			<OsmIcon class="icon" />
			{{ t('integration_openstreetmap', 'OpenStreetMap integration') }}
		</h2>
		<div id="osm-content">
			<div id="osm-search-block">
				<NcCheckboxRadioSwitch
					:checked="state.search_location_enabled && state.admin_search_location_enabled"
					:disabled="!state.admin_search_location_enabled"
					@update:checked="onCheckboxChanged($event, 'search_location_enabled')">
					{{ t('integration_openstreetmap', 'Enable searching for locations') }}
				</NcCheckboxRadioSwitch>
				<br>
				<p v-if="state.search_location_enabled && state.admin_search_location_enabled" class="settings-hint">
					<InformationOutlineIcon :size="20" class="icon" />
					{{ t('integration_openstreetmap', 'Warning, everything you type in the Unified Search menu will be sent to OpenStreetMap\'s Nominatim service.') }}
				</p>
				<p v-if="state.admin_search_location_enabled === false" class="settings-hint">
					<InformationOutlineIcon :size="20" class="icon" />
					{{ t('integration_tmdb', 'A Nextcloud administrator has disabled the OpenStreetMap Unified Search provider') }}
				</p>
				<NcCheckboxRadioSwitch
					:checked="state.link_preview_enabled"
					@update:checked="onCheckboxChanged($event, 'link_preview_enabled')">
					{{ t('integration_openstreetmap', 'Enable OpenStreetMap link previews') }}
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch v-if="state.link_preview_enabled"
					:checked="state.prefer_simple_osm_iframe"
					@update:checked="onCheckboxChanged($event, 'prefer_simple_osm_iframe')">
					{{ t('integration_openstreetmap', 'Prefer simple OpenStreetMap frame') }}
				</NcCheckboxRadioSwitch>
			</div>
			<NcCheckboxRadioSwitch
				:checked="state.navigation_enabled"
				@update:checked="onCheckboxChanged($event, 'navigation_enabled')">
				{{ t('integration_openstreetmap', 'Enable navigation link') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

import OsmIcon from './icons/OsmIcon.vue'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
		OsmIcon,
		NcCheckboxRadioSwitch,
		InformationOutlineIcon,
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
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
