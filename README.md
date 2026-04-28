# SoundRepo

Gestor de Biblioteca Musical Desktop desenvolvido para o Módulo 16 (Projeto de Software) do curso TGPSI.

## Contexto

Este projeto foi criado como trabalho final da disciplina PSI A. O objetivo era desenvolver uma aplicação desktop funcional que demonstrasse competências em programação, bases de dados relacionais e design de interfaces.

Inicialmente pensei em fazer em C# WinForms, mas para conseguir um visual moderno (tipo Spotify) acabei por usar PHP Desktop - basicamente um executável Windows que corre uma aplicação web localmente.

## O que faz

É um gestor de música completo onde podes:
- Importar músicas (uma a uma ou pasta inteira)
- Auto-preencher metadados via iTunes API
- Organizar por artista/álbum/género
- Reproduzir com player customizado (shuffle, repeat, fila)
- Navegar sem parar a música (SPA com router JS)

## Stack Técnica

- **Backend**: PHP 8.3 com PDO
- **Base de Dados**: MySQL (3NF - Artists → Albums → Musics)
- **Frontend**: HTML5, CSS3 (Grid/Flexbox, variáveis CSS), JavaScript Vanilla
- **Desktop**: PHP Desktop Chrome (CEF)
- **APIs**: iTunes Search API para metadados

## Instalação

1. Importa o `database.sql` no MySQL (via phpMyAdmin ou linha de comandos)
2. Se não usas `root` sem password, edita `www/db.php`
3. Executa `SoundRepo.exe`

Pronto. A app abre numa janela desktop e conecta-se ao MySQL local.

## Estrutura

```
soundrepo/
├── SoundRepo.exe       # Executável
├── database.sql        # Schema da BD
├── php/                # Runtime PHP 8.3
└── www/                # Código-fonte
    ├── index.php       # Página principal
    ├── adicionar.php   # Form de upload
    ├── api.php         # Endpoints (delete, etc)
    ├── db.php          # Conexão MySQL
    └── css/js/         # Frontend
```

## Features Técnicas

- **SPA Router**: Navegação AJAX sem recarregar (música continua a tocar)
- **Smart Upload**: Extrai "Artista - Titulo" do nome do ficheiro e consulta iTunes API
- **Bulk Import**: Importa pastas inteiras com `webkitdirectory`
- **LocalStorage**: Guarda estado do player (música atual, fila, posição)
- **Context Menu**: Botão direito para adicionar à fila ou apagar
- **Column Browser**: Filtros dinâmicos (género/artista/álbum)

## Notas

As pastas `covers/` e `musicas/` são criadas automaticamente quando adicionas a primeira música.
