import './bootstrap';
import { createApp } from 'vue';
import App from './App.vue';
import AppHeader from './components/AppHeader.vue';
import AppFooter from './components/AppFooter.vue';
import AppToast from './components/AppToast.vue';
import UnsavedChangesDialog from './components/UnsavedChangesDialog.vue';
import DocumentActionsMenu from './components/DocumentActionsMenu.vue';
import ConfirmActionDialog from './components/ConfirmActionDialog.vue';

createApp(App)
    .component('AppHeader', AppHeader)
    .component('AppFooter', AppFooter)
    .component('AppToast', AppToast)
    .component('UnsavedChangesDialog', UnsavedChangesDialog)
    .component('DocumentActionsMenu', DocumentActionsMenu)
    .component('ConfirmActionDialog', ConfirmActionDialog)
    .mount('#app');
