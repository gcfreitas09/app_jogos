# App Jogos (PHP + MySQL)

Plataforma web para conectar jogadores de esportes recreativos com convites, inscricoes, fila de espera e busca por proximidade.

## Modulos principais

- `Explorar` (`explore.php`)
  - Filtros por esporte, periodo, raio e somente com vagas
  - Captura de localizacao no navegador
  - Cards com distancia, vagas e CTA dinamico (Entrar / Entrar na fila / Sair)
- `Meus Jogos` (`my_games.php`)
  - Inscritos/aceitos
  - Criados por mim
  - Fila de espera
  - Historico
- `Criar Convite` (`create_invite.php`)
  - esporte, data/hora, local, endereco, lat/lng, vagas, preco, regras
- `Perfil` (`profile.php`)
  - esportes preferidos
  - raio padrao
  - permissao de localizacao

## Regras de negocio implementadas

- Login e sessao por `$_SESSION['user_id']`
- Inscricao com transacao + `SELECT ... FOR UPDATE`
- Fila de espera automatica (`role = waitlist`, `position`)
- Promocao automatica da fila quando um jogador sai
- Status derivado no app: Aberto / Completo / Encerrado
- Notificacao de jogo completo com flag anti-reenvio (`completed_notified_at`)

## Estrutura de pastas

- `config/`
- `src/Core/`
- `src/Repositories/`
- `src/Services/`
- `templates/`
- `api/v1/`
- `database/schema.sql`

## Banco de dados

Configurado em `config/app.php`:

- banco: `app_jogos`
- usuario: `root`
- senha: vazia

## Setup no XAMPP

1. Importe `database/schema.sql` no MySQL.
2. Inicie Apache e MySQL.
3. Acesse:
   - `http://localhost/app_jogos/register.php`
   - `http://localhost/app_jogos/login.php`
   - `http://localhost/app_jogos/explore.php`

## API inicial

- `GET /app_jogos/api/v1/invites.php?sport=Padel&period=week&only_with_slots=1&radius_km=10&lat=-30.03&lng=-51.21`
- `GET /app_jogos/api/v1/invite.php?id=1`
- `GET /app_jogos/api/v1/my_games.php`
- `POST /app_jogos/api/v1/join.php` com `invite_id`
- `POST /app_jogos/api/v1/leave.php` com `invite_id`

Atualmente a autenticacao da API usa sessao web.
