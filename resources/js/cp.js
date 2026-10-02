const pages = import.meta.glob('./pages/**/*.vue', { eager: true });

Statamic.booting(() => {
    for (const [path, module] of Object.entries(pages)) {
        const name = path.replace('./pages/', '').replace('.vue', '');
        Statamic.$inertia.register(`radpack-crm::${name}`, module.default);
    }
});
