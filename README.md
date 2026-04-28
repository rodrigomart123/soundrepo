# SoundRepo

Gestor de Biblioteca Musical Desktop desenvolvido com PHP Desktop (Chromium Embedded Framework) e MySQL.

## Características

- Interface moderna inspirada no Spotify
- Gestão completa de músicas, álbuns e artistas
- Player de áudio integrado
- Filtros por género, artista e álbum
- Upload de capas de álbuns
- Base de dados MySQL local

## Requisitos

- Windows (testado no Windows 10/11)
- MySQL Server (ou XAMPP/WAMP)
- PHP 8.3+ (incluído no pacote PHP Desktop)

## Instalação

### 1. Configurar a Base de Dados

Certifique-se de que o MySQL está a correr e execute o ficheiro `database.sql`:

```bash
mysql -u root -p < database.sql
```

Ou importe manualmente através do phpMyAdmin/MySQL Workbench.

### 2. Configurar Credenciais (Opcional)

Se as suas credenciais MySQL não forem as padrão (root sem senha), edite o ficheiro `www/db.php`:

```php
$user = 'root';     // Seu utilizador MySQL
$pass = '';         // Sua senha MySQL
```

### 3. Executar a Aplicação

Simplesmente execute o ficheiro `SoundRepo.exe`.

A aplicação irá:
- Iniciar um servidor PHP local
- Abrir a interface numa janela desktop
- Conectar-se à base de dados MySQL

## Estrutura do Projeto

```
soundrepo/
├── SoundRepo.exe          # Executável principal (PHP Desktop)
├── database.sql           # Schema da base de dados
├── settings.json          # Configurações da aplicação
├── php/                   # PHP 8.3 runtime
└── www/                   # Código-fonte da aplicação
    ├── index.php          # Página principal
    ├── adicionar.php      # Formulário de adicionar músicas
    ├── api.php            # API REST (delete, etc)
    ├── db.php             # Configuração da base de dados
    ├── css/               # Estilos
    ├── js/                # JavaScript
    ├── covers/            # Capas de álbuns (gerado)
    └── musicas/           # Ficheiros de áudio (gerado)
```

## Utilização

1. **Adicionar Música**: Clique em "Adicionar Música" na sidebar
2. **Reproduzir**: Clique duas vezes numa música na lista
3. **Filtrar**: Use os filtros de Género, Artista e Álbum
4. **Eliminar**: Clique no ícone de lixo ao lado da música

## Tecnologias

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 8.3
- **Base de Dados**: MySQL 8.0+
- **Desktop**: PHP Desktop (CEF - Chromium Embedded Framework)
- **Ícones**: Phosphor Icons

## Notas de Desenvolvimento

- As pastas `covers/` e `musicas/` são criadas automaticamente
- Os ficheiros de áudio e capas são armazenados localmente
- A base de dados usa UTF-8 (utf8mb4) para suportar caracteres especiais

## Licença

Este projeto é de código aberto. Sinta-se livre para usar e modificar.
