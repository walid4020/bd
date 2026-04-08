// code fait suite à la séance 6 d'applications web

const { createApp, ref } = Vue;

createApp({
    data() {
        return {
            // Stocke les infos de l'utilisateur connecté (null si déconnecté)
            currentUser: null,
            // Stocke les messages d'erreur à afficher dans le formulaire
            error: '',
            // Les données du formulaire de connexion
            // On utilise "email" pour correspondre au v-model HTML
            login_form: ref ({ email: '', password: '', remember: false })
        }
    },

    // S'exécute automatiquement au chargement de la page
    mounted() {
        this.refresh();
    },

    methods: {
        // FONCTION DE CONNEXION 
        login() {
            this.error = ''; // On réinitialise l'erreur

            // Vérification basique côté client
            if (!this.login_form.email || !this.login_form.password) {
                this.error = 'Veuillez remplir tous les champs.';
                return;
            }

            // Appel au fichier PHP que nous avons créé
            fetch('api/backend_login.php?action=login', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: this.login_form.email,
                    password: this.login_form.password,
                    remember: this.login_form.remember
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Si succès, on enregistre l'utilisateur dans currentUser
                    this.currentUser = data.user;
                    // On vide le formulaire
                    this.login_form = { email: '', password: '', remember: false };
                } else {
                    // Sinon on affiche l'erreur renvoyée par le PHP
                    this.error = data.error || 'Identifiants incorrects';
                }
            })
            .catch(error => {
                this.error = 'Erreur réseau : ' + error.message;
            });
        },

        // FONCTION DE DÉCONNEXION 
        logout() {
            fetch('api/backend_login.php?action=logout', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(() => {
                // On remet currentUser à null pour réafficher le formulaire
                this.currentUser = null;
            })
            .catch(error => {
                console.error('Erreur lors de la déconnexion :', error.message);
            });
        },

        // VÉRIFICATION DE LA SESSION (Automatique) 
        refresh() {
            fetch('api/backend_login.php?action=whoami', { 
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                // Si le serveur dit qu'on est déjà connecté (via session PHP)
                if (data.loggedIn) {
                    this.currentUser = data.user;
                } else if (data.remembered_user) {
                    // Si un cookie existe, on pourrait pré-remplir l'email
                    this.login_form.email = data.remembered_user;
                }
            })
            .catch(error => {
                console.error('Erreur de rafraîchissement :', error.message);
            });
        }
    }
}).mount('#app');