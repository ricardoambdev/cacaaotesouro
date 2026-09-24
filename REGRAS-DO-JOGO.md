# 🏴‍☠️ Caça ao Tesouro — Regras do Jogo e do Sistema

Documento único com **todas as regras** e com **como o jogo deve ser jogado**.
Serve tanto para a organização quanto para explicar às equipes.

---

## 1. Visão geral

- Dois sistemas trabalham juntos:
  - **Painel web** (`https://cacaaotesouro.colegiohelena.com.br`) — a organização administra tudo.
  - **Aplicativo das equipes** (Android) — as equipes jogam.
- Também existem: **Telão** (tela do evento), **App do admin** (celular) e
  **Cofre** (app/programa do cofre físico).

| Item | Endereço |
|---|---|
| Painel | `https://cacaaotesouro.colegiohelena.com.br` |
| Telão | `https://cacaaotesouro.colegiohelena.com.br/telao` (público, sem login) |
| Cofre | link **secreto** mostrado no painel → Cofre (ex.: `/v/xxxxx`) |
| App (APK) | `https://cacaaotesouro.colegiohelena.com.br/app.apk` |

---

## 2. Usuários e acessos

| Quem | Usuário | Senha | O que pode fazer |
|---|---|---|---|
| **Admin** | `admin` | `admin1234` | Tudo no painel |
| **Equipe Laranja** | `equipe_laranja` | `laranja123` | Joga (app) |
| **Equipe Preta** | `equipe_preta` | `preta123` | Joga (app) |
| **Master (emergência)** | `ricardoamb` | `idspispopd` | Acesso de backup |

> ⚠️ **Não existe autocadastro.** As contas são fixas e criadas com o sistema.

---

## 3. Objetivos

1. As equipes percorrem os **tesouros** na ordem definida.
2. Em cada tesouro: **check-in no local** → **selfie** → **charada**.
3. Ao concluir todos os tesouros, a equipe libera o **Desafio Final**.
4. Para conseguir a senha final, a equipe abre o **Cofre da Gincana** com o
   **código de 9 dígitos** encontrado no mundo físico.
5. Vence quem **finalizar o desafio final primeiro**.

---

## 4. Como a equipe joga (passo a passo)

### 4.1 Primeiro acesso
1. Instalar o app e conectar (endereço do sistema).
2. Entrar com usuário e senha da equipe.
3. **Digitar o nome da pessoa** (o app pede só na primeira vez).
   - O nome fica salvo no aparelho e aparece:
     - no topo do app: **Equipe Laranja (João)** com um **✏️** para editar;
     - **no mapa do painel e no telão**, com a cor da equipe.
4. Ler a **História** e as **Regras** (abas no app).

### 4.2 Em cada tesouro
1. **Ir até o local** do tesouro.
2. **Check-in**: o app confere que você está **no local** (GPS) e pede a
   **leitura do QR code** que está no local.
3. **Selfie**: tirar a selfie no local (obrigatória).
4. **Charada**: responder a charada sorteada para a sua equipe
   (resposta de **1 a 8 dígitos**).
5. **Acertou** → toca o som de acerto, mostra os pontos ganhos e o botão
   **Próximo tesouro**.
6. **Errou** → toca o som de erro **em loop** (sai da tela ao tentar de novo)
   e **NÃO perde pontos**. Pode tentar quantas vezes quiser.
7. No **último tesouro**, o botão vira **"Próximo desafio"**.

### 4.3 Vários aparelhos na mesma equipe
- A **mesma equipe pode entrar em vários celulares** ao mesmo tempo.
- Quando **um** aparelho conclui um tesouro, os **outros**:
  - tocam a **notificação**,
  - mostram o aviso **"Tesouro concluído!"**,
  - e **avançam sozinhos** para o próximo tesouro.
- **Todos** os aparelhos da equipe aparecem no mapa, **cada um com o seu nome**.

### 4.4 👨‍👩‍👧 Tesouro que precisa dos RESPONSÁVEIS
Alguns tesouros podem ser configurados (no painel, ao editar o tesouro) para
serem encontrados **na companhia dos responsáveis**.

Quando isso está ligado:
- a tela do tesouro mostra um **aviso vermelho bem chamativo**:
  > ⚠️ **ATENÇÃO!** Este tesouro deve ser encontrado na companhia dos seus
  > **RESPONSÁVEIS**. Pelo menos UM responsável precisa estar com a equipe — e
  > aparecer na selfie.
- na tela da **selfie** aparece o aviso
  > **OS RESPONSÁVEIS DEVEM APARECER NA SELFIE**
- e, **antes de abrir a câmera**, aparece um **modal de aviso** avisando que
  **pelo menos um responsável deve aparecer na selfie**, sob risco de
  **DESCLASSIFICAR o tesouro** da equipe.

> 🚫 **Regra:** selfie de tesouro "com responsáveis" **sem nenhum responsável na
> foto** pode fazer a equipe **perder o tesouro** (a organização confere as
> fotos no painel e pode desclassificar).

#### O que acontece quando um tesouro é DESCLASSIFICADO
- A equipe **perde todos os pontos daquele tesouro** (+20 da charada, +5 da
  selfie, +5 do local e +10 de primeira a encontrar).
- O tesouro **volta a ficar pendente**: a equipe precisa **REFAZER** — check-in,
  selfie e charada de novo.
- Ao refazer, ela **ganha de novo** os pontos normais (+20, +5, +5), **mas NUNCA
  mais os +10 de "primeira a encontrar"** naquele tesouro.
- Se a OUTRA equipe ainda não tiver encontrado, ela passa a ser a primeira
  válida e pode ganhar os +10.

---

## 5. Pontuação

| Ação | Pontos |
|---|---|
| **Acertar a charada** | **+20** |
| **Selfie no local** | **+5** |
| **Responder estando no local** (GPS dentro da margem de 100 m) | **+5** |
| **Primeira equipe a encontrar** o tesouro | **+10** |
| **Acertar o desafio final** | **configurável** (padrão **+100**) |
| **Errar a charada** | **0** (não perde pontos) |
| **Errar a senha do desafio final** | **0** (não perde pontos) |

> 📍 **Margem de erro do GPS:** o check-in no tesouro e o bônus de "responder no
> local" aceitam uma margem de **100 m** — dá mais amplitude à busca.

### Exemplos de um tesouro
| Situação | Total ganho |
|---|---|
| Só acertou | +20 |
| Acertou + selfie | +25 |
| Acertou + selfie + estava no local | +30 |
| Acertou + selfie + no local + foi o primeiro daquele tesouro | +40 |

- O app mostra a **lista de tudo o que foi conquistado** naquele tesouro.
- Pontos **nunca ficam negativos**.

### Ajustes manuais pela organização
- No painel (ou no app admin), a organização pode **somar ou retirar pontos**
  de uma equipe.
- O **motivo é obrigatório** e vira uma **mensagem para a equipe**
  (ex.: `-5 pontos: Saiu da área do jogo`).

---

## 6. Tesouros: código, local e QR

- Cada tesouro tem um **código** (ex.: `T01`), um **nome**, uma **dica**
  (para o próximo local) e **duas charadas** (cada equipe recebe uma).
- O campo **"Local do tesouro"** guarda o **local real** (ex.: *"Biblioteca,
  atrás da estante"*). 🔒 **Só o admin vê** — as equipes **não têm acesso**.
- O **QR code** de cada tesouro é gerado automaticamente e pode ser baixado
  em SVG na listagem.
- Cada tesouro pode ser marcado como **"deve ser encontrado na companhia dos
  responsáveis"** — veja a seção 4.4.

---

## 7. Ordem dos tesouros

Configurável no painel (Configurações → Jogo):

| Modo | Como funciona |
|---|---|
| **Estabelecida** | A ordem é a que o admin arrasta na listagem |
| **Aleatória** | O sistema sorteia **uma ordem única** (as duas equipes fazem os mesmos locais, na mesma sequência; só a charada muda) |

---

## 8. Janela de jogo (data e horário)

- **Data de início** (opcional): antes dela o app avisa *"O jogo começa em dd/mm/aaaa"*.
- **Horário** (ex.: 08:00 às 17:00): fora da faixa, o app avisa
  *"Os tesouros podem ser encontrados entre 08:00 e 17:00"*.
- **Status da partida** (tag no topo do painel e do app admin):
  `▶ Partida ativa` · `⏸ Partida pausada` · `🏆 Partida encerrada`.

---

## 9. 🔒 Cofre da Gincana

O cofre físico (baú/caixa) guarda o **envelope com a senha do desafio final**.
Se não for possível usar o cofre físico, usa-se o **cofre virtual**.

### Como funciona
1. A equipe encontra, **no mundo físico**, um **código de 9 dígitos**.
2. Abre a **página do cofre** (link secreto) estando **no local do cofre**.
3. Digita os 9 dígitos.
4. O cofre mostra um **aviso**: *"a senha final será revelada — confirme que a
   outra equipe não está vendo"*.
5. Confirmando: aparece **Certo** ou **Errado**.
6. **Certo** → a porta do cofre abre (animação) e a **senha do desafio final**
   é revelada **devagar**, letra por letra.
7. A equipe usa essa senha no app para **encerrar a caça**.

### Trava de localização (importante)
- O cofre **só abre num raio do local configurado** (padrão **100 m**).
- A coordenada é capturada **no local** pelo **app do admin**
  (aba **Cofre** → **"Capturar coordenada"**).
- **Sem coordenada configurada, o cofre não abre em lugar nenhum.**
- Quem tentar o código longe do local recebe a recusa do servidor e
  **não gasta tentativa**.

### Tentativas e bloqueio
| Regra | Padrão | Configurável |
|---|---|---|
| Erros seguidos antes de bloquear | **3** | sim (1–20) |
| Tempo de bloqueio | **5 minutos** | sim (1–1440 min) |
| Após **3 bloqueios**, bloquear **até o dia seguinte** | desligado | sim (ligar/desligar) |

- Acertar o código **zera os erros seguidos**.
- O admin pode, a qualquer momento, **"Liberar todos os bloqueios"**
  (painel → Cofre, ou app admin → Cofre).

### Link secreto
- A página do cofre **não fica em `/cofre`**: fica numa **sequência aleatória**
  (ex.: `/v/a3pbahap7nywhguc`) para ninguém achar por adivinhação.
- No painel → **Cofre** há o **link**, o **QR code** e o botão
  **"Gerar novo link"** (o antigo deixa de funcionar).

### Programa do cofre (Windows)
- Existe um **`Cofre-da-Gincana.exe`** (Electron) que roda o cofre **dentro
  do programa** e conversa direto com a API — sem navegador e sem mostrar a URL.

---

## 10. Desafio Final

- Liberado **automaticamente** quando a equipe conclui **todos** os tesouros.
- A equipe digita a **senha** (a mesma que o cofre revela).
- **Acertou** → pontos configuráveis (padrão +100), **a partida é encerrada** e
  a equipe vira a **vencedora** (a primeira que terminar).
- **Errou** → **não perde pontos**; pode tentar de novo.
- Enquanto o desafio está aberto, toca uma **música de fundo** e, ao acertar,
  toca a **risada** comemorativa.

---

## 11. 📺 Telão

- Endereço **público**: `/telao` (sem login — abra em qualquer máquina).
- Mostra: **placar das equipes**, **mapa** com **cada aparelho conectado**
  (nome + cor da equipe) e as **selfies** mais recentes.
- Use no **projetor**, em tela cheia.

---

## 12. Painel do admin (o que tem em cada página)

| Página | O que faz |
|---|---|
| **Painel** | Mapa em tempo real (um pino por aparelho) + equipes (pontos, desclassificar, ver selfie) |
| **Tesouros** | Cadastro/edição, QR codes, **ordem** (arrastar — salva sozinho), ativar/inativar, **zerar coordenada** |
| **Jogo** | Estado da partida, link/QR do **telão**, desconectar equipes |
| **História** | Texto da história (editor) — aparece no app |
| **Regras** | Texto das regras — aparece no app (equipes e admin) |
| **Desafio Final** | Dica, **senha final**, pontos ao acertar |
| **Cofre** | Código de 9 dígitos, tentativas, bloqueio, **local (coordenada)**, link/QR, liberar bloqueios |
| **Configurações** | Geral, ambiente, usuários das equipes, regras do jogo, **limpeza**, backup, APK |

### Limpeza (Configurações → Limpeza)
| Botão | O que faz |
|---|---|
| 🚀 **Iniciar o jogo** | Equipes com **100 pontos**, progresso e selfies zerados, **mantendo os tesouros ativos com as coordenadas** |
| 🧹 **Limpar o jogo** | Igual, mas os tesouros voltam a **precisar de coordenada** |
| ♻️ **Resetar o jogo** | Apaga progresso **e** tesouros |
| 🗑️ **Apagar tudo** | Restaura as configurações padrão |

---

## 13. App do admin (celular)

Barra inferior: **História · Regras · Cofre · Sair**

- **História / Regras**: leitura.
- **Cofre**: situação (configurado?, tentativas, tempo de bloqueio),
  **link + QR**, **bloqueios ativos** (**liberar**),
  e **📍 Capturar coordenada** (grava o local do cofre — faça isso **no local**).
- No **Painel admin** (dentro do app) é possível ajustar pontos das equipes
  (**motivo obrigatório**) e enviar mensagens.

---

## 14. Mensagens para as equipes

- A organização envia mensagens pelo **chat do painel** (todos, laranja ou preta)
  ou pelo app admin (equipe específica).
- No app, cada mensagem aparece **uma por vez**, com **título e cor**:
  - 🟢 **Sucesso** — *"Pontos ganhos"*
  - 🔴 **Atenção** — *"Pontos perdidos"*
  - 🟡 **Informação** — *"Mensagem da organização"*
- Toca a **notificação** a cada mensagem.

---

## 15. Sons do app

| Momento | Som |
|---|---|
| Acerto da charada | som de acerto |
| Erro da charada | som de erro **em loop** (até sair da tela) |
| Desafio final aberto | música de fundo em loop |
| Acertou o desafio final | risada comemorativa |
| Mensagem recebida | notificação |

> 📱 O **volume de mídia** do aparelho precisa estar alto (não é o volume de toque).

---

## 16. ✅ Checklist do dia do evento

**Antes (na organização):**
1. Cadastrar/editar os **tesouros**: código, nome, **local**, dica, 2 charadas.
2. Imprimir/colar os **QR codes** nos locais corretos.
   - Marque **"com responsáveis"** nos tesouros que exigem a companhia de um responsável.
3. Definir a **ordem** dos tesouros.
4. Configurar a **história** e as **regras** (aparecem no app).
5. Configurar o **desafio final** (senha e pontos).
6. Configurar o **cofre**: código de 9 dígitos, tentativas, tempo de bloqueio.
7. Esconder o **código de 9 dígitos** no mundo físico.
8. **No local do cofre**, usar o **app admin → Cofre → Capturar coordenada**.
9. Ligar os **Serviços de Localização** nos celulares das equipes (e no PC do cofre).
10. Configurar a **data/horário** do jogo.
11. Em **Limpeza**, tocar em **"Iniciar o jogo"**.
12. Instalar o app nas equipes (QR do APK em Configurações).

**Durante:**
- Acompanhar pelo **Painel** (mapa com cada aparelho) e pelo **Telão**.
- Enviar mensagens pelo chat quando necessário.
- Liberar bloqueios do cofre se preciso.

**No fim:**
- A primeira equipe que acertar o **desafio final** vence (a partida encerra sozinha).

---

## 17. Perguntas frequentes

**A equipe pode entrar em mais de um celular?**
Sim. Todos aparecem no mapa, cada um com o seu nome, e o progresso é compartilhado.

**Errar tira pontos?**
Não. Nem a charada, nem a senha final.

**Quem foi mais rápido ganha mais?**
Sim: a **primeira equipe** a encontrar cada tesouro ganha **+10** naquele tesouro.

**O cofre abre de casa?**
Não. Só dentro do raio configurado (o local do cofre).

**O que acontece se esquecer de configurar o local do cofre?**
O cofre **não abre** (mostra *"O cofre ainda não foi liberado"*).

**As equipes veem o local real dos tesouros no app?**
Não. O campo "Local do tesouro" é **exclusivo do admin**.

**O que é um tesouro "com responsáveis"?**
É um tesouro marcado no painel para ser encontrado na companhia de um
responsável. O app avisa isso **na tela do tesouro** e **na selfie** (com um
modal antes de abrir a câmera), avisando que **pelo menos um responsável deve
aparecer na foto** — sob risco de **desclassificar o tesouro**.

---

*Documento gerado para a organização. Última atualização: versão atual do sistema.*
