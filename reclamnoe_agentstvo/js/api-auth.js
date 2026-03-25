/**
 * API клиент для работы с PHP backend
 * Заменяет localStorage на AJAX запросы к серверу
 */

class ApiClient {
    constructor() {
        this.baseUrl = 'api/';
        this.csrfToken = '';
    }
    
    // Инициализация CSRF токена
    async init() {
        try {
            const response = await fetch(this.baseUrl + 'auth.php?action=check');
            const data = await response.json();
            if (data.success) {
                this.csrfToken = this.getCSRFFromPage();
            }
        } catch (e) {
            console.log('API not available, using localStorage fallback');
        }
    }
    
    getCSRFFromPage() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }
    
    // POST запрос
    async post(action, data = {}) {
        const formData = new FormData();
        formData.append('csrf_token', this.csrfToken);
        
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        const response = await fetch(this.baseUrl + action + '.php?action=' + action, {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    // GET запрос
    async get(action, params = {}) {
        const url = new URL(this.baseUrl + action + '.php', window.location.origin);
        url.searchParams.append('action', action);
        
        for (const [key, value] of Object.entries(params)) {
            url.searchParams.append(key, value);
        }
        
        const response = await fetch(url);
        return await response.json();
    }
}

const api = new ApiClient();

// Система авторизации с поддержкой PHP API
class AuthSystem {
    constructor() {
        this.currentUser = null;
        this.initSync();
    }
    
    initSync() {
        // Сначала проверяем localStorage (синхронно)
        this.initLocalStorageFallback();
        
        // Затем пробуем сервер (асинхронно, не блокируем)
        this.checkSessionAsync();
        
        this.updateUI();
    }
    
    async checkSessionAsync() {
        try {
            const response = await fetch('api/auth.php?action=check');
            const data = await response.json();
            
            if (data.success && data.user) {
                this.currentUser = data.user;
                this.updateUI();
            }
        } catch (e) {
            // Используем localStorage
        }
    }
    
    initLocalStorageFallback() {
        const userJson = localStorage.getItem('marketing_agency_current_user');
        if (userJson) {
            this.currentUser = JSON.parse(userJson);
        }
        
        this.initDefaultUsers();
    }
    
    initDefaultUsers() {
        const users = this.getUsers();
        
        const adminExists = users.some(user => user.username === 'admin');
        
        if (!adminExists) {
            const defaultUsers = [
                {
                    id: 1,
                    username: 'admin',
                    password: 'cot_admin456',
                    email: 'admin@inside360.ru',
                    name: 'Администратор',
                    role: 'admin',
                    registrationDate: new Date().toISOString()
                },
                {
                    id: 2,
                    username: 'demo',
                    password: 'demo123',
                    email: 'demo@example.com',
                    name: 'Демо Пользователь',
                    role: 'user',
                    registrationDate: new Date().toISOString()
                }
            ];
            
            localStorage.setItem('marketing_agency_users', JSON.stringify(defaultUsers));
        }
    }
    
    getUsers() {
        const usersJson = localStorage.getItem('marketing_agency_users');
        return usersJson ? JSON.parse(usersJson) : [];
    }
    
    saveUsers(users) {
        localStorage.setItem('marketing_agency_users', JSON.stringify(users));
    }
    
    async register(username, password, email, name, phone = '') {
        // Пробуем через API
        try {
            const data = await api.post('auth', {
                username,
                password,
                email,
                name,
                phone,
                action: 'register'
            });
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateUI();
                return { success: true, message: data.message };
            }
            
            return { success: false, message: data.message };
        } catch (e) {
            // Fallback на localStorage
            return this.registerLocal(username, password, email, name);
        }
    }
    
    registerLocal(username, password, email, name) {
        const users = this.getUsers();
        
        if (users.some(user => user.username === username)) {
            return { success: false, message: 'Пользователь с таким логином уже существует' };
        }
        
        if (users.some(user => user.email === email)) {
            return { success: false, message: 'Пользователь с таким email уже существует' };
        }
        
        const newUser = {
            id: Date.now(),
            username,
            password,
            email,
            name,
            phone,
            role: 'user',
            registrationDate: new Date().toISOString()
        };
        
        users.push(newUser);
        this.saveUsers(users);
        
        this.currentUser = newUser;
        localStorage.setItem('marketing_agency_current_user', JSON.stringify(newUser));
        this.updateUI();
        
        return { success: true, message: 'Регистрация успешна!' };
    }
    
    async login(username, password) {
        // Пробуем через API
        try {
            const data = await api.post('auth', {
                username,
                password,
                action: 'login'
            });
            
            if (data.success) {
                this.currentUser = data.user;
                this.updateUI();
                return { success: true, message: data.message };
            }
            
            return { success: false, message: data.message };
        } catch (e) {
            // Fallback на localStorage
            return this.loginLocal(username, password);
        }
    }
    
    loginLocal(username, password) {
        const users = this.getUsers();
        const user = users.find(u => u.username === username && u.password === password);
        
        if (user) {
            this.currentUser = user;
            localStorage.setItem('marketing_agency_current_user', JSON.stringify(user));
            this.updateUI();
            return { success: true, message: 'Вход выполнен успешно' };
        }
        
        return { success: false, message: 'Неверный логин или пароль' };
    }
    
    async logout() {
        // Пробуем через API
        try {
            await api.post('auth', { action: 'logout' });
        } catch (e) {
            // Игнорируем ошибку
        }
        
        // Fallback
        this.currentUser = null;
        localStorage.removeItem('marketing_agency_current_user');
        this.updateUI();
        
        return { success: true, message: 'Выход выполнен' };
    }
    
    updateUI() {
        const authNav = document.getElementById('authNav');
        const loginLink = document.getElementById('loginLink');
        
        if (authNav && loginLink) {
            if (this.currentUser) {
                if (this.currentUser.role === 'admin') {
                    authNav.innerHTML = `
                        <li class="nav-item dropdown">
                            <a href="#" class="nav-link" id="userMenu">
                                <i class="fas fa-user-shield"></i> ${this.currentUser.name}
                            </a>
                            <div class="dropdown-menu">
                                <a href="admin.html" class="dropdown-item">Админ-панель</a>
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
                                <a href="profile.html" class="dropdown-item">Личный кабинет</a>
                                <a href="new-request.html" class="dropdown-item">Новая заявка</a>
                                <div class="dropdown-divider"></div>
                                <a href="#" id="logoutBtn" class="dropdown-item">Выход</a>
                            </div>
                        </li>
                    `;
                }
                
                const logoutBtn = document.getElementById('logoutBtn');
                if (logoutBtn) {
                    logoutBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.logout();
                        window.location.href = 'index.html';
                    });
                }
                
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
        
        if (this.currentUser && this.currentUser.role === 'admin') {
            const navMenu = document.getElementById('navMenu');
            if (navMenu) {
                const adminLinkExists = Array.from(navMenu.children).some(
                    li => li.querySelector('a[href="admin.html"]')
                );
                
                if (!adminLinkExists) {
                    const adminLi = document.createElement('li');
                    adminLi.innerHTML = '<a href="admin.html" class="nav-link">Админ-панель</a>';
                    navMenu.appendChild(adminLi);
                }
            }
        }
    }
    
    getCurrentUser() {
        return this.currentUser;
    }
    
    getUserRequests(userId) {
        // Fallback на localStorage
        const requests = JSON.parse(localStorage.getItem('marketing_agency_requests')) || [];
        if (!userId) return [];
        return requests.filter(request => request.userId === userId);
    }
    
    createRequest(requestData) {
        return this.createRequestLocal(requestData);
    }
    
    createRequestLocal(requestData) {
        const requests = JSON.parse(localStorage.getItem('marketing_agency_requests')) || [];
        
        // Получаем userId из текущего пользователя
        const userId = this.currentUser ? this.currentUser.id : null;
        
        const newRequest = {
            id: Date.now(),
            userId: userId,
            userName: this.currentUser ? this.currentUser.name : 'Гость',
            name: requestData.title || 'Заявка',
            service: requestData.service || 'smm',
            phone: requestData.phone || '',
            email: this.currentUser ? this.currentUser.email : '',
            description: requestData.description || requestData.message || '',
            title: requestData.title || '',
            message: requestData.message || '',
            status: 'new',
            date: new Date().toISOString()
        };
        
        requests.push(newRequest);
        localStorage.setItem('marketing_agency_requests', JSON.stringify(requests));
        
        return { success: true, request_id: newRequest.id, message: 'Заявка создана!' };
    }
    
    updateRequestStatus(requestId, newStatus) {
        const requests = JSON.parse(localStorage.getItem('marketing_agency_requests')) || [];
        const requestIndex = requests.findIndex(r => r.id === requestId);
        
        if (requestIndex !== -1) {
            requests[requestIndex].status = newStatus;
            localStorage.setItem('marketing_agency_requests', JSON.stringify(requests));
            return true;
        }
        
        return false;
    }
    
    getAllRequests() {
        return JSON.parse(localStorage.getItem('marketing_agency_requests')) || [];
    }
    
    getAllUsers() {
        return this.getUsers();
    }
}

const auth = new AuthSystem();

if (typeof module !== 'undefined' && module.exports) {
    module.exports = auth;
} else {
    window.auth = auth;
}
