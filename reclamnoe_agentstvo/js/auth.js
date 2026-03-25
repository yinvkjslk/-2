
/**
 * Система авторизации INSIDE360
 * Работает с PHP API (server-side)
 */

class AuthSystem {
    constructor() {
        this.apiUrl = 'api/auth.php';
        this.currentUser = null;
    }
    
    // Инициализация - проверка сессии
    async init() {
        await this.checkSession();
        return this.currentUser;
    }
    
    // Регистрация нового пользователя
    async register(username, email, password, name, phone = '') {
        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('email', email);
            formData.append('password', password);
            formData.append('name', name);
            formData.append('phone', phone);
            formData.append('csrf_token', this.getCsrfFromCookie());

            const response = await fetch(`${this.apiUrl}?action=register`, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                this.currentUser = data.user;
                this.updateUI();
            }

            return data;
        } catch (error) {
            console.error('Ошибка регистрации:', error);
            return { success: false, message: 'Ошибка соединения с сервером' };
        }
    }
    
    // Вход пользователя
    async login(username, password) {
        try {
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('csrf_token', this.getCsrfFromCookie());

            const response = await fetch(`${this.apiUrl}?action=login`, {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                this.currentUser = data.user;
                this.updateUI();
            }

            return data;
        } catch (error) {
            console.error('Ошибка входа:', error);
            return { success: false, message: 'Ошибка соединения с сервером' };
        }
    }

    // Выход пользователя
    async logout() {
        try {
            await fetch(`${this.apiUrl}?action=logout`, {
                method: 'POST',
                credentials: 'include'
            });

            this.currentUser = null;
            this.updateUI();
            return { success: true, message: 'Выход выполнен' };
        } catch (error) {
            console.error('Ошибка выхода:', error);
            return { success: false, message: 'Ошибка соединения' };
        }
    }

    // Проверка сессии
    async checkSession() {
        try {
            const response = await fetch(`${this.apiUrl}?action=check`, {
                method: 'GET',
                credentials: 'include'
            });

            const data = await response.json();

            if (data.success) {
                this.currentUser = data.user;
                this.updateUI();
                return this.currentUser;
            }

            this.currentUser = null;
            return null;
        } catch (error) {
            console.error('Ошибка проверки сессии:', error);
            return null;
        }
    }

    // Получение CSRF токена из cookie
    getCsrfFromCookie() {
        const name = 'csrf_token=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const ca = decodedCookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return '';
    }

    // Получение текущего пользователя
    getCurrentUser() {
        return this.currentUser;
    }

    // Проверка роли
    isAdmin() {
        return this.currentUser?.role === 'admin';
    }

    isManager() {
        return this.currentUser?.role === 'admin' || this.currentUser?.role === 'manager';
    }

    // Обновление UI
    updateUI() {
        const authNav = document.getElementById('authNav');

        if (!authNav) return;

        if (this.currentUser) {
            const isAdmin = this.currentUser.role === 'admin';
            
            if (isAdmin) {
                authNav.innerHTML = `
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link" id="userMenu">
                            <i class="fas fa-user-shield"></i> ${this.currentUser.name}
                        </a>
                        <div class="dropdown-menu">
                            <a href="admin.html" class="dropdown-item">Админ-панель</a>
                            <a href="profile.html" class="dropdown-item">Профиль</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" id="logoutBtn" class="dropdown-item">Выход</a>
                        </div>
                    </li>
                `;
            } else {
                authNav.innerHTML = `
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link" id="userMenu">
                            <i class="fas fa-user"></i> ${this.currentUser.name}
                        </a>
                        <div class="dropdown-menu">
                            <a href="profile.html" class="dropdown-item">Мои заявки</a>
                            <a href="new-request.html" class="dropdown-item">Новая заявка</a>
                            <div class="dropdown-divider"></div>
                            <a href="#" id="logoutBtn" class="dropdown-item">Выход</a>
                        </div>
                    </li>
                `;
            }

            // Обработчик выхода
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.logout().then(() => {
                        window.location.href = 'index.html';
                    });
                });
            }

            // Обработчик dropdown
            const userMenu = document.getElementById('userMenu');
            if (userMenu) {
                userMenu.addEventListener('click', (e) => {
                    e.preventDefault();
                    const dropdown = userMenu.nextElementSibling;
                    dropdown.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!authNav.contains(e.target)) {
                        const dropdowns = document.querySelectorAll('.dropdown-menu');
                        dropdowns.forEach(dropdown => {
                            dropdown.classList.remove('show');
                        });
                    }
                });
            }
        } else {
            authNav.innerHTML = '<a href="login.html" class="nav-link" id="loginLink">Вход</a>';
        }
    }

    // Работа с заявками
    async getUserRequests() {
        try {
            const response = await fetch('api/requests.php?action=getMy', {
                method: 'GET',
                credentials: 'include'
            });
            const data = await response.json();
            return data.success ? data.requests : [];
        } catch (e) {
            console.error('Ошибка получения заявок:', e);
            return [];
        }
    }

    async createRequest(requestData) {
        try {
            const formData = new FormData();
            Object.keys(requestData).forEach(key => {
                formData.append(key, requestData[key]);
            });
            formData.append('csrf_token', this.getCsrfFromCookie());

            const response = await fetch('api/requests.php?action=create', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });

            return await response.json();
        } catch (e) {
            console.error('Ошибка создания заявки:', e);
            return { success: false, message: 'Ошибка соединения' };
        }
    }
}

// Глобальный экземпляр
const auth = new AuthSystem();

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    auth.init();
});

// Экспорт
if (typeof module !== 'undefined' && module.exports) {
    module.exports = auth;
} else {
    window.auth = auth;
}