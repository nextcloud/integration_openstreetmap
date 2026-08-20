/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

async function pickLocation({ mountPoint = null, isInsideViewer = false }) {
	const { createApp } = await import('vue')
	const { default: MapLocationPickerModal } = await import('./views/MapLocationPickerModal.vue')

	return new Promise((resolve, reject) => {
		if (OCA.Osm.isLocationPickerOpen) {
			reject(new Error('Location picker is already open'))
			return
		}
		OCA.Osm.isLocationPickerOpen = true

		let modalMountPoint
		const content = document.querySelector('#content') ?? document.querySelector('#content-vue')

		if (mountPoint !== null) {
			// if a mount point is specified, always use it
			modalMountPoint = mountPoint
		} else {
			const modalId = 'osmLocationPickerModal'
			modalMountPoint = document.createElement('div')
			modalMountPoint.id = modalId
			// the default mount point location is different whether the assistant is opened from the viewer or not
			if (isInsideViewer) {
				// so the assistant modal is opened on top of the current viewer
				document.querySelector('body').append(modalMountPoint)
				modalMountPoint.classList.add('insideViewer')
			} else {
				// so the viewer can be later opened on top of the assistant
				document.querySelector('body').insertBefore(modalMountPoint, content.nextSibling)
			}
		}

		const app = createApp(
			MapLocationPickerModal,
			{
				isInsideViewer,
			},
		)
		app.mixin({ methods: { t, n } })
		const view = app.mount(modalMountPoint)

		modalMountPoint.addEventListener('close', () => {
			app.unmount()
			OCA.Osm.isLocationPickerOpen = false
			reject(new Error('User cancellation'))
		})

		modalMountPoint.addEventListener('submit', (data) => {
			console.debug('submit', data.detail)
			app.unmount()
			OCA.Osm.isLocationPickerOpen = false
			resolve(data.detail)
		})
	})
}

function init() {
	if (!OCA.Osm) {
		/**
		 * @namespace
		 */
		OCA.Osm = {
			pickLocation,
		}
	}
}

init()
