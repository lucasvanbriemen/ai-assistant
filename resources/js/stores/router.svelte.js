import Chat from '../pages/Home.svelte';
import page from 'page';

const routes = {
    '/': Chat
};

export const router = $state({
    currentComponent: Chat,
    params: {},
});

export function initRouter() {
    page('/', () => {
        router.currentComponent = routes['/'];
        router.params = {};
    });

    page('*', () => {
        page.redirect('/');
    });

    page.start();
}
