import 'ninja-keys';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('ninja-keys').forEach(cmdk => {
        cmdk.data = [
            {
                id: 'Clients',
                title: 'Create a new client',
                section: 'Clients',
                handler: () => {
                    // it's auto register above hotkey with this handler
                    alert('Your logic to handle');
                },
            },
            {
                id: 'Theme',
                title: 'Change theme',
                children: [
                    {
                        id: 'Light Theme',
                        title: 'Change theme to Light',
                        parent: 'Theme',
                        handler: () => {
                            document.body.classList.remove('theme-dark');
                            localStorage.setItem('theme', "light");
                        },
                    },
                    {
                        id: 'Dark Theme',
                        title: 'Change theme to Dark',
                        parent: 'Theme',
                        handler: () => {
                            document.body.classList.add('theme-dark');
                            localStorage.setItem('theme', "dark");
                        },
                    },
                ]
            },
        ];
    })
});