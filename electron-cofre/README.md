# Cofre da Gincana — app Windows (Electron)

Programa que abre **somente a página do Cofre** em tela cheia, sem barra de
endereços e sem mostrar a URL. Serve para deixar no PC que vai abrir o cofre.

## Como rodar em desenvolvimento

```bash
cd electron-cofre
npm install
npm start
```

## Como gerar o .exe

```bash
cd electron-cofre
npm install
npm run dist
```

O executável sai em `dist/Cofre-da-Gincana.exe` (portable, ~71 MB).

## A URL do cofre

A URL **não** aparece na interface. Ela é lida de um `config.json` colocado
**ao lado do executável**:

```json
{ "url": "https://SEU-SITE/v/SEGREDO" }
```

Sem o arquivo, usa a URL padrão definida em `main.js` (`DEFAULT_URL`).
Assim, quando gerar um link novo no painel (Cofre → Gerar novo link),
basta trocar o `config.json` — sem recompilar.

## Atalhos

| Tecla | Ação |
|---|---|
| `F11` | alterna tela cheia |
| `F5` | recarrega a página |
| `Ctrl+Shift+Q` | fecha o programa |

## Observações

- Links externos (que abririam em outra aba) são enviados para o navegador
  padrão, não abrem dentro do programa.
- A página do cofre exige estar dentro do raio configurado (100 m do local):
  o PC precisa ter **Serviços de Localização** ligados no Windows e autorizar
  a localização no primeiro acesso.
