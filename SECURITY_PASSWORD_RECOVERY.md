# Segurança de Recuperação de Senha - Anna Insights

## 📋 Visão Geral

Este documento descreve as medidas de segurança implementadas no fluxo de recuperação de senha da Anna Insights, garantindo que:
- ✅ Usuários possam resetar suas senhas de forma segura
- ✅ Usuários NÃO possam alterar senhas de outros usuários
- ✅ Tokensinha expirem após 1 hora
- ✅ Tokens sejam usados apenas uma vez
- ✅ Nenhuma enumeração de emails

---

## 🔒 Arquitetura de Segurança

### 1. **Geração de Token**

Quando um usuário solicita recuperação de senha via POST `/api/password-recovery`:

```
1. Backend verifica se o email existe (sem revelar resultado publicamente)
2. Se existe, gera token seguro:
   - Tipo: 32 bytes hexadecimais (64 caracteres)
   - Gerado com: bin2hex(random_bytes(32))
   - Totalmente aleatório e criptograficamente seguro
3. Token armazenado no banco de dados com:
   - user_id: Vínculo único ao usuário
   - token: String de 64 caracteres
   - expires_at: Timestamp (hora atual + 3600 segundos)
   - used: FALSE (inicialmente)
4. Uma URL é enviada por email com o token
```

**Estrutura do Banco de Dados:**

```sql
table password_reset_tokens {
  id (PK)
  user_id (FK -> usuarios.id)      -- CRÍTICO: Vínculo ao usuário
  token (unique, 64 chars)          -- Token criptograficamente seguro
  expires_at (timestamp)            -- TTL de 1 hora
  used (boolean, default FALSE)     -- Flag de uso único
}
```

### 2. **Validação do Frontend**

Quando a URL é clicada pelo usuário:

```javascript
// PasswordRecovery.jsx
const token = searchParams.get('token');

// O componente valida o token ANTES de mostrar o formulário
await api.post('/validate-reset-token', { token });
// Se token for inválido/expirado → mostra erro
// Se válido → mostra formulário de nova senha
```

**Por que isso importa:** Impede que usuários vejam formulários de reset para tokens inválidos.

### 3. **Resetar a Senha**

POST `/api/reset-password` com payload:
```json
{
  "token": "64-character-hex-string",
  "password": "nova_senha_segura",
  "password_confirmation": "nova_senha_segura"
}
```

**Processo no Backend:**

```php
// 1. Busca o token NO BANCO DE DADOS
SELECT id, user_id, expires_at, used 
FROM password_reset_tokens 
WHERE token = ? AND used = FALSE

// 2. Valida:
if (!$token) → erro "Token inválido ou já utilizado"
if (expirado) → erro "Token expirado"

// 3. IMPORTANTE: Usa o user_id DO TOKEN para atualizar APENAS esse usuário
UPDATE usuarios 
SET senha = password_hash($data['password']) 
WHERE id = $token['user_id']  // ← user_id vem do token, não do usuário

// 4. Marca como used
UPDATE password_reset_tokens 
SET used = TRUE 
WHERE id = $token['id']
```

---

## 🛡️ Por Que Usuários NÃO Podem Alterar Senhas Alheias?

### Cenário 1: Usuário A tenta mudar senha do Usuário B

**Ataque esperado:** User A entra na screen de reset, tira um token válido vencido e tenta usar para resetar senha de B.

**Proteção:** 
- Cada token está VINCULADO PERMANENTEMENTE a um user_id específico
- Quando o token foi gerado (recoverPassword), a função:
  ```php
  $stmt = $db->prepare(
    'SELECT id, nome, email FROM usuarios WHERE email = ?'
  );
  $stmt->execute([$data['email']]);
  $user = $stmt->fetch();
  
  // User A digitou email de B
  // Se B não existe ou está inativo → nada acontece (resposta genérica)
  // Se B existe:
  INSERT INTO password_reset_tokens (user_id, token, ...)
  // user_id aqui será SEMPRE o de B
  ```

- User A não consegue "roubar" um token de B porque:
  1. User A não tem acesso à caixa de email de B
  2. O link único é enviado apenas para o email de B
  3. Se User A tenta usar um token que não é dele → será do Usuário B

Portanto, **mesmo que tivesse um token válido de B, só conseguiria resetar a senha de B com esse token, não de si mesmo.**

### Cenário 2: Usuário tenta modificar payload

**Ataque esperado:** User A tem um token e tenta mudar o `user_id` no request.

**Proteção:**
- O backend NÃO lê user_id do request
- O backend busca o token no BD e lê o user_id **do registro do token**
- User_id vem do banco, não do client

```php
// Backend IGNORA qualquer user_id no request
$data = Router::getJsonPayload(); // {"token": "...", "password": "..."}

// Backend busca o token E LÊ O user_id do BD
$stmt = $db->prepare(
  'SELECT id, user_id, ... FROM password_reset_tokens WHERE token = ?'
);
$stmt->execute([$data['token']]); // Só usa o token do request
$token = $stmt->fetch();

// Usa o user_id DO BANCO, não do request
UPDATE usuarios SET senha = ? WHERE id = $token['user_id']
```

### Cenário 3: Ataque de Replay

**Ataque esperado:** User tira um link de reset, usa uma vez, depois tenta usar novamente.

**Proteção:** 
- Token marcado como `used = TRUE` após primeiro uso
- Segunda tentativa:
  ```php
  SELECT ... FROM password_reset_tokens WHERE token = ? AND used = FALSE
  // Query não retorna nada porque used = TRUE
  → Erro: "Token inválido ou já utilizado"
  ```

---

## 📱 Fluxo Completo com Segurança

```
┌─────────────────────────────────────────────────────────────┐
│ 1. USUÁRIO ESQUECEU SENHA                                   │
└─────────────────────────────────────────────────────────────┘
        ↓
        Login → "Esqueci minha senha" → input email
        ↓
        POST /api/password-recovery {"email": "user@example.com"}

┌─────────────────────────────────────────────────────────────┐
│ 2. BACKEND PROCESSA                                         │
└─────────────────────────────────────────────────────────────┘
        ↓
        SELECT user WHERE email = ? AND status_ativo = 'S'
        ↓
        IF user não existe → resposta genérica (sem revelar)
        ↓
        IF user existe:
          - Gera token = bin2hex(random_bytes(32))
          - Calcula expires_at = agora + 3600s
          - INSERT password_reset_tokens (user_id, token, expires_at, used=FALSE)
          - Envia email com: http://localhost:3000/reset-password?token=XXXXX

┌─────────────────────────────────────────────────────────────┐
│ 3. USUÁRIO RECEBE EMAIL E CLICA NO LINK                    │
└─────────────────────────────────────────────────────────────┘
        ↓
        Browser abre: /reset-password?token=XXXXX
        ↓
        PasswordRecovery.jsx valida antes de mostrar form:
          POST /api/validate-reset-token {"token": "XXXXX"}

┌─────────────────────────────────────────────────────────────┐
│ 4. VALIDAÇÃO DO TOKEN                                       │
└─────────────────────────────────────────────────────────────┘
        ↓
        SELECT * FROM password_reset_tokens 
        WHERE token = ? AND used = FALSE
        ↓
        IF não encontra → erro "Token inválido"
        ↓
        IF encontra e expirado → erro "Token expirado"
        ↓
        IF encontra e válido → sucesso ✓

┌─────────────────────────────────────────────────────────────┐
│ 5. USUÁRIO DIGITA NOVA SENHA                               │
└─────────────────────────────────────────────────────────────┘
        ↓
        Validações Frontend:
          - Mínimo 8 caracteres
          - Confirmação deve ser igual
        ↓
        POST /api/reset-password {
          "token": "XXXXX",
          "password": "nova_senha",
          "password_confirmation": "nova_senha"
        }

┌─────────────────────────────────────────────────────────────┐
│ 6. BACKEND ATUALIZA SENHA                                   │
└─────────────────────────────────────────────────────────────┘
        ↓
        SELECT * FROM password_reset_tokens 
        WHERE token = ? AND used = FALSE
        
        IF válido e não expirado:
          - hashedPassword = password_hash(senha, PASSWORD_BCRYPT)
          - UPDATE usuarios 
              SET senha = hashedPassword 
              WHERE id = $token['user_id']  ← SEGURO: do BD
          - UPDATE password_reset_tokens 
              SET used = TRUE 
              WHERE id = token_id
          - Erro 200 OK "Senha atualizada"

┌─────────────────────────────────────────────────────────────┐
│ 7. USUÁRIO FAZ LOGIN COM NOVA SENHA                        │
└─────────────────────────────────────────────────────────────┘
        ↓
        Navigate /login
        ↓
        username + new_password
        ↓
        POST /api/login
          - Busca user por login
          - password_verify(sent_password, stored_hash) ✓
          - Gera JWT real
          - localStorage token + user
          - Redireciona para dashboard
```

---

## 🔐 Proteções Adicionais Implementadas

### Frontend

1. **Route Protection (App.jsx)**
   ```jsx
   <Route 
     path="/reset-password" 
     element={<PasswordRecovery />} 
   />
   // Qualquer um pode acessar (é necessário ter o token)
   
   <Route 
     path="/chat" 
     element={user ? <ChatHome /> : <Navigate to="/login" />} 
   />
   // Requer autenticação
   ```

2. **Token no URL, não em localStorage**
   - Token vem como query param: `/reset-password?token=...`
   - Impossível compartilhar sem revelar a URL completa
   - Expira em 1 hora no backend

3. **Validação Real-Time**
   ```jsx
   useEffect(() => {
     validateToken(); // Verifica ANTES de mostrar form
   }, [token]);
   ```

### Backend

1. **Authorization Middleware**
   ```php
   // routes.php
   $router->get('/api/users', function() { 
     AuthMiddleware::requireAdmin();  // ← Autorização
     (new UserController())->index(); 
   });
   ```

2. **JWT Implementation**
   - Tokens com expiração real
   - Assinatura HMAC-SHA256
   - Payload inclui: id, login, nome, perfil

3. **Generic Error Messages**
   - POST /password-recovery sempre retorna sucesso
   - Previne descoberta de emails válidos

---

## ✅ Checklist de Segurança

- [x] Tokens gerados com bin2hex(random_bytes(32)) - criptograficamente seguro
- [x] Tokens expiram após 1 hora
- [x] Tokens marcados como used = TRUE após uso
- [x] User_id vem do BD, não do request
- [x] Cada token vinculado permanentemente a um user_id
- [x] Sem enumeração de emails
- [x] Senhas com bcrypt (PASSWORD_BCRYPT)
- [x] Sem tokens em localStorage (apenas JWT de session)
- [x] Rotas protegidas requerem autenticação
- [x] Admin endpoints requerem privilégios
- [x] Frontend valida token antes de form
- [x] Endpoints de reset não autenticados, mas seguros

---

## 📊 Comparação: Antes vs Depois

| Aspecto | Antes | Depois |
|---------|-------|--------|
| Autenticação | Mock Token | JWT Real com HMAC-SHA256 |
| Proteção de Rotas | Nenhuma | Middleware AuthMiddleware |
| Admin Endpoints | Sem proteção | Requer perfil = 1 |
| Chat Endpoints | Sem proteção | Requer token válido |
| Password Recovery | URL ficava fora das rotas | Integrada e segura |
| Tokens | Mockados | Aleatórios, 1 uso, 1 hora |

---

## 🚀 Como Testar

### Teste 1: Password Recovery Normal

```bash
# 1. Solicitar recuperação
curl -X POST http://localhost/anna_insights-api/api/password-recovery \
  -H "Content-Type: application/json" \
  -d '{"email": "user@example.com"}'
# Resposta: {"message": "Se o e-mail existir..."}

# 2. Verificar email (veja o token no BD)
SELECT token FROM password_reset_tokens 
WHERE user_id = (SELECT id FROM usuarios WHERE email = 'user@example.com')

# 3. Usar o token
curl -X POST http://localhost/anna_insights-api/api/validate-reset-token \
  -H "Content-Type: application/json" \
  -d '{"token": "copied_from_db"}'
# Resposta: {"valid": true}

# 4. Reset senha
curl -X POST http://localhost/anna_insights-api/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token": "copied_from_db",
    "password": "new_password_123",
    "password_confirmation": "new_password_123"
  }'
# Resposta: {"message": "Senha atualizada com sucesso..."}

# 5. Verificar que token virou used
SELECT used FROM password_reset_tokens WHERE token = 'copied_from_db'
# Resultado: TRUE
```

### Teste 2: Segurança - Token Usado Duas Vezes

```bash
# Mesmo token reusado
curl -X POST http://localhost/anna_insights-api/api/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token": "same_token",
    "password": "another_password_456",
    "password_confirmation": "another_password_456"
  }'
# Resposta: {"error": "Token inválido ou já utilizado"} ✓
```

### Teste 3: Endpoints Protegidos

```bash
# Sem token
curl -X GET http://localhost/anna_insights-api/api/users \
  -H "Content-Type: application/json"
# Resposta: {"error": "Token não encontrado"} ✓

# Com token inválido
curl -X GET http://localhost/anna_insights-api/api/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer invalid_token"
# Resposta: {"error": "Token inválido ou expirado"} ✓

# Com token válido mas não-admin
curl -X GET http://localhost/anna_insights-api/api/users \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer valid_user_token"
# Resposta: {"error": "Acesso negado. Privilégios de administrador necessários."} ✓
```

---

## 📌 Conclusão

O sistema implementado garante que:

> **✅ Usuários podem ressetar suas senhas de forma segura**
>
> **✅ Usuários NÃO podem alterar senhas de outros usuários**
>
> **✅ Todos os endpoints críticos estão protegidos**
>
> **✅ Tokens são seguros, únicos e temporários**
