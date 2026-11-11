/**
 * Home hero (owner: hero; upgrade spec §12 B1–B5). Two tiny Alpine components that load their real code
 * on demand, so the hero's JavaScript is paid only where the hero is shown:
 *   d2HeroStage  <x-d2.hero.visual>  → ../hero/stage.js (the pile's entrance, document switch, callouts, pager,
 *                                       autoplay, pointer depth)
 *   d2HeroTool   <x-d2.hero.tool>    → ../hero/tool.js  (audience filter, documents, prefill)
 * If the stage script cannot load, the armed class is removed so the composed final state shows
 * (the boot script's safety timeout covers a script that never arrives).
 */
export default (Alpine) => {
    Alpine.data('d2HeroStage', () => {
        let unmount = null;
        return {
            init() {
                const root = this.$root;
                import('../hero/stage.js')
                    .then(({ mount }) => {
                        unmount = mount(root);
                    })
                    .catch((error) => {
                        document.documentElement.classList.remove('hero-armed');
                        console.error(error);
                    });
            },
            destroy() {
                if (unmount) unmount();
            },
        };
    });

    Alpine.data('d2HeroTool', () => {
        let unmount = null;
        return {
            init() {
                const root = this.$root;
                import('../hero/tool.js')
                    .then(({ mount }) => {
                        unmount = mount(root);
                    })
                    .catch((error) => console.error(error));
            },
            destroy() {
                if (unmount) unmount();
            },
        };
    });
};
