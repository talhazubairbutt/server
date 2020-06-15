<template>
	<div id="app-dashboard">
		<h2>{{ greeting.icon }} {{ greeting.text }}, {{ name }}</h2>

		<Container class="panels"
			orientation="horizontal"
			drag-handle-selector=".panel--header"
			@drop="onDrop">
			<Draggable v-for="panelId in layout" :key="panels[panelId].id" class="panel">
				<div class="panel--header">
					<a :href="panels[panelId].url">
						<h3 :class="panels[panelId].iconClass">
							{{ panels[panelId].title }}
						</h3>
					</a>
				</div>
				<div :ref="panels[panelId].id" :data-id="panels[panelId].id" />
			</Draggable>
		</Container>
		<a class="add-panels icon-add" @click="showModal">Add more panels</a>
		<Modal v-if="modal" @close="closeModal">
			<div class="modal__content">
				<transition-group name="flip-list" tag="ol">
					<li v-for="panel in sortedPanels" :key="panel.id">
						<input :id="'panel-checkbox-' + panel.id"
							type="checkbox"
							class="checkbox"
							:checked="isActive(panel)"
							@input="updateCheckbox(panel, $event.target.checked)">
						<label :for="'panel-checkbox-' + panel.id">
							{{ panel.title }}
						</label>
					</li>
					<li key="appstore">
						<a href="/index.php/apps/settings" class="button">{{ t('dashboard', 'Get more panels from the app store') }}</a>
					</li>
				</transition-group>
			</div>
		</Modal>
	</div>
</template>

<script>
import Vue from 'vue'
import { loadState } from '@nextcloud/initial-state'
import { getCurrentUser } from '@nextcloud/auth'
import { Modal } from '@nextcloud/vue'
import { Container, Draggable } from 'vue-smooth-dnd'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const panels = {
	...loadState('dashboard', 'panels'),
	calendar: { id: 'calendar', iconClass: 'icon-calendar-dark', title: 'Calendar' },
	talk: { id: 'talk', iconClass: 'icon-talk', title: 'Talk' },
	mail: { id: 'mail', iconClass: 'icon-mail', title: 'Mail' },
}

const applyDrag = (arr, dragResult) => {
	const { removedIndex, addedIndex, payload } = dragResult
	if (removedIndex === null && addedIndex === null) return arr

	const result = [...arr]
	let itemToAdd = payload

	if (removedIndex !== null) {
		itemToAdd = result.splice(removedIndex, 1)[0]
	}

	if (addedIndex !== null) {
		result.splice(addedIndex, 0, itemToAdd)
	}

	return result
}

export default {
	name: 'App',
	components: {
		Modal,
		Container,
		Draggable,
	},
	data() {
		return {
			timer: new Date(),
			callbacks: {},
			panels,
			name: getCurrentUser()?.displayName,
			layout: loadState('dashboard', 'layout'),
			modal: false,
		}
	},
	computed: {
		greeting() {
			const time = this.timer.getHours()

			if (time > 18) {
				return { icon: '🌙', text: t('dashboard', 'Good evening') }
			}
			if (time > 12) {
				return { icon: '☀', text: t('dashboard', 'Good afternoon') }
			}
			if (time === 12) {
				return { icon: '🍽', text: t('dashboard', 'Time for lunch') }
			}
			if (time > 5) {
				return { icon: '🌄', text: t('dashboard', 'Good morning') }
			}
			return { icon: '🦉', text: t('dashboard', 'Have a night owl') }
		},
		isActive() {
			return (panel) => this.layout.indexOf(panel.id) > -1
		},
		sortedPanels() {
			return Object.values(this.panels).sort((a, b) => {
				const indexA = this.layout.indexOf(a.id)
				const indexB = this.layout.indexOf(b.id)
				if (indexA === -1 || indexB === -1) {
					return indexB - indexA || a.id - b.id
				}
				return indexA - indexB || a.id - b.id
			})
		},
	},
	watch: {
		callbacks() {
			this.rerenderPanels()
		},
	},
	mounted() {
		setInterval(() => {
			this.timer = new Date()
		}, 30000)
	},
	methods: {
		/**
		 * Method to register panels that will be called by the integrating apps
		 *
		 * @param {string} app The unique app id for the widget
		 * @param {function} callback The callback function to register a panel which gets the DOM element passed as parameter
		 */
		register(app, callback) {
			Vue.set(this.callbacks, app, callback)
		},
		rerenderPanels() {
			for (const app in this.callbacks) {
				const element = this.$refs[app]
				if (this.panels[app].mounted) {
					continue
				}
				if (element) {
					this.callbacks[app](element[0])
					Vue.set(this.panels[app], 'mounted', true)
				} else {
					console.error('Failed to register panel in the frontend as no backend data was provided for ' + app)
				}
			}
		},

		saveLayout() {
			axios.post(generateUrl('/apps/dashboard/layout'), {
				layout: this.layout.join(','),
			})
		},
		onDrop(dropResult) {
			this.layout = applyDrag(this.layout, dropResult)
			this.saveLayout()
		},
		showModal() {
			this.modal = true
		},
		closeModal() {
			this.modal = false
		},
		updateCheckbox(panel, currentValue) {
			const index = this.layout.indexOf(panel.id)
			if (!currentValue && index > -1) {
				this.layout.splice(index, 1)

			} else {
				this.layout.push(panel.id)
			}
			Vue.set(this.panels[panel.id], 'mounted', false)
			this.saveLayout()
			this.$nextTick(() => this.rerenderPanels())
		},
	},
}
</script>

<style lang="scss" scoped>
	#app-dashboard {
		width: 100%;
	}
	h2 {
		text-align: center;
		font-size: 32px;
		line-height: 130%;
		padding: 80px 16px 32px;
	}

	.panels {
		width: 100%;
		display: flex;
		justify-content: center;
		flex-direction: row;
		align-items: top;
		flex-wrap: wrap;
	}

	.panel, .panels > div {
		width: 280px;
		padding: 16px;

		.panel--header h3 {
			cursor: grab;
			&:active {
				cursor: grabbing;
			}
		}

		& > .panel--header {
			position: sticky;
			top: 50px;
			background: linear-gradient(var(--color-main-background-translucent), var(--color-main-background-translucent) 80%, rgba(255, 255, 255, 0));
			backdrop-filter: blur(4px);
			display: flex;
			a {
				flex-grow: 1;
			}

			h3 {
				display: block;
				flex-grow: 1;
				margin: 0;
				font-size: 20px;
				font-weight: bold;
				background-size: 32px;
				background-position: 10px 10px;
				padding: 16px 8px 16px 52px;
			}
		}
	}

	.add-panels {
		position: fixed;
		bottom: 20px;
		right: 20px;
		padding: 10px;
		padding-left: 35px;
		padding-right: 15px;
		background-position: 10px center;
		border-radius: 100px;
		&:hover {
			background-color: var(--color-background-hover);
		}
	}

	.modal__content {
		width: 30vw;
		margin: 20px;
		ol {
			list-style-type: none;
		}
		li label {
			padding: 10px;
			display: block;
			list-style-type: none;
		}
	}

	.flip-list-move {
		transition: transform 1s;
	}

	// FIXME: Remove this as it is only for mockups
	.panel::v-deep {

		.icon-activity {
			background-image: url('data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjxzdmcgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBoZWlnaHQ9IjMyIiB3aWR0aD0iMzIiIHZlcnNpb249IjEuMCIgdmlld2JveD0iMCAwIDMyIDMyIj4KIDxwYXRoIGQ9Im0xNiAxLTEwIDE4aDExbC0xIDEyIDEwLTE4aC0xMXoiLz4KPC9zdmc+');
			background-size: 16px;
		}

		.activity--icon {
			float: left;
			margin-right: 4px;
		}

		.activity--timestamp {
			font-size: 90%;
			color: var(--color-text-maxcontrast);
		}

		.event {
			list-style-type: none;
			border: 1px solid #eee;
			border-left: 10px solid #c00;
			border-radius:5px;
			padding: 3px;
			margin: 0;
			line-height: 100%
		}
		.event:hover {
			background-color:#fafafa;
		}
		.event * {
			padding:5px;
		}
		h4 {
			margin: 0;
		}
		& > div:not(.panel--header) h3 {
			color: #666;
			font-weight: 300;
			font-size: 100%;
			margin-top: 30px;
			display: none;
		}

		.avatar {
			margin: 3px;
			width: 32px;
			height: 32px;
			background-color: #fa7;
			color: #fff;
			border-radius: 50%;
			font-size: 16pt;
			padding: 8px;
			padding-bottom: 9px;
			padding-top: 7px;
			flex-shrink: 0;
			text-align: center;
		}

		.line {
			display: flex;
			width: 100%;
			padding: 3px;
			margin-bottom: 5px;
		}
		.line:hover {
			border-radius: 3px;
			background-color: #f6f6f6;
		}
		.side {
			background-color: red;
			width: 6px;
			border-radius: 3px;
			overflow: hidden;
			flex-shrink: 0;
		}

		button.primary {
			background-color: var(--color-success);
			color: #fff;

			border: 0;
			border-radius: 100px;
			height: 34px;
		}
		.details {
			flex-grow: 1;
		}

		h4, h5 {
			margin: 0;
			padding: 3px;
			font-weight: 300;
			margin-left: 10px;
			font-size: 100%;
		}
		h5 {
			color: #666;
		}
		small {
			float: right;
			padding-top: 5px;
			color: #666;
		}

		.calendar .line {
			opacity: 0.5;
		}
		.calendar .line:first-child {
			opacity: 1;
		}

	}
</style>
