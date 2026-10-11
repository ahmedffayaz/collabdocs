const http = window.axios;
const routeTemplates = JSON.parse(document.getElementById('app').dataset.routes);

function documentUrl(template, id) {
    return template.replace('__DOCUMENT__', encodeURIComponent(id));
}

export const api = {
    getWorkspace: () => http.get(routeTemplates.documents),
    switchUser: (payload) => http.post(routeTemplates.activeUser, payload),
    createDocument: (payload) => http.post(routeTemplates.createDocument, payload),
    importDocument: (payload) => http.post(routeTemplates.importDocument, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
    }),
    getDocument: (id) => http.get(documentUrl(routeTemplates.document, id)),
    updateDocument: (id, payload) => http.put(documentUrl(routeTemplates.updateDocument, id), payload),
    deleteDocument: (id) => http.delete(documentUrl(routeTemplates.deleteDocument, id)),
    shareDocument: (id, payload) => http.post(documentUrl(routeTemplates.shareDocument, id), payload),
};
