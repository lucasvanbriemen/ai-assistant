import About from '../pages/About.svelte';
import Chat from '../pages/Chat.svelte';
import Home from '../pages/Home.svelte';
import page from 'page';

const routes = {
    '/': Home,
    '/about': About,
    '/chat': Chat,
};

export const router = $state({
    currentComponent: Home,
    params: {},
});

export function initRouter() {
    page('/', () => {
        router.currentComponent = routes['/'];
        router.params = {};
    });

    page('/about', () => {
        router.currentComponent = routes['/about'];
        router.params = {};
    });

    page('/chat', () => {
        router.currentComponent = routes['/chat'];
        router.params = {};
    });

    page('*', () => {
        page.redirect('/');
    });

    page.start();
}
