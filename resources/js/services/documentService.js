import { api } from '../api/api.js';

function unwrap(response) {
    return response.data.data;
}

export const documentService = {
    async getWorkspace() {
        return unwrap(await api.getWorkspace());
    },

    async switchUser(payload) {
        return unwrap(await api.switchUser(payload));
    },

    async createDocument(payload) {
        return unwrap(await api.createDocument(payload));
    },

    async importDocument(payload) {
        return unwrap(await api.importDocument(payload));
    },

    async getDocument(id) {
        return unwrap(await api.getDocument(id));
    },

    async updateDocument(id, payload) {
        return unwrap(await api.updateDocument(id, payload));
    },

    async deleteDocument(id) {
        return unwrap(await api.deleteDocument(id));
    },

    async shareDocument(id, payload) {
        return unwrap(await api.shareDocument(id, payload));
    },
};
