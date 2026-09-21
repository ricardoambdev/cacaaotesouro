import 'package:audioplayers/audioplayers.dart';

/// Serviço de sons do jogo.
///
/// É um SINGLETON: existe um único conjunto de players para todo o app.
///
/// ⚠️ NUNCA chame `dispose()` a partir de uma tela. O player é destruído e
/// passa a falhar silenciosamente nas reproduções seguintes (era a causa
/// dos sons "pararem de tocar"). Para parar os sons de uma tela use
/// [stopAll] / [stopLoop] / [stopBackgroundMusic].
class SoundService {
  static final SoundService _instance = SoundService._();
  factory SoundService() => _instance;
  SoundService._();

  // ── Assets ────────────────────────────────────────────────
  static const String acertoSound = 'sounds/acerto.mp3';
  static const String choroSound = 'sounds/choro.mp3';
  static const String notificacaoSound = 'sounds/notificacao.mp3';
  static const String bgDesafioSound = 'sounds/bg_desafio.mp3';
  static const String risadaSound = 'sounds/risada.mp3';
  static const String aliEstaSound = 'sounds/ali-esta.mp3';

  /// Player dos efeitos (um som por vez).
  AudioPlayer? _effectsPlayer;

  /// Player do som de erro, que fica em LOOP.
  AudioPlayer? _loopPlayer;

  /// Player da música de fundo (desafio final).
  AudioPlayer? _backgroundPlayer;

  /// Qual player recriar caso tenha sido descartado.
  AudioPlayer _effects() => _effectsPlayer ??= AudioPlayer();
  AudioPlayer _loop() => _loopPlayer ??= AudioPlayer();
  AudioPlayer _background() => _backgroundPlayer ??= AudioPlayer();

  /// Executa [action] em um player, recriando-o se estiver descartado.
  ///
  /// O `audioplayers` lança exceção quando o player já sofreu `dispose()`;
  /// nesse caso criamos um novo player e tentamos mais uma vez.
  Future<void> _guard(AudioPlayer? Function() current, AudioPlayer Function() recreate,
      void Function(AudioPlayer?) reset, Future<void> Function(AudioPlayer) action) async {
    try {
      await action(current()!);
    } catch (_) {
      try {
        reset(null);
        await action(recreate());
      } catch (_) {
        // Som é acessório: falha silenciosa.
      }
    }
  }

  // ── Efeitos (um tiro) ─────────────────────────────────────

  /// Toca um asset de som uma única vez. Para qualquer reprodução anterior.
  Future<void> play(String asset) async {
    await _guard(
      () => _effects(),
      () => _effectsPlayer = AudioPlayer(),
      (p) => _effectsPlayer = p,
      (player) async {
        await player.stop();
        await player.setReleaseMode(ReleaseMode.release);
        await player.play(AssetSource(asset));
      },
    );
  }

  Future<void> playAcerto() => play(acertoSound);

  /// Toca o som de erro UMA única vez (ex.: erro no desafio final).
  /// Para o erro da charada, que fica em loop, use [playChoroLoop].
  Future<void> playChoro() => play(choroSound);

  Future<void> playNotification() => play(notificacaoSound);

  Future<void> playRisada() => play(risadaSound);

  Future<void> playAliEsta() => play(aliEstaSound);

  // ── Erro em LOOP ──────────────────────────────────────────

  /// Toca o som de erro em LOOP, até [stopLoop] ser chamado (ex.: ao sair da
  /// tela de resultado).
  Future<void> playChoroLoop() async {
    await _guard(
      () => _loop(),
      () => _loopPlayer = AudioPlayer(),
      (p) => _loopPlayer = p,
      (player) async {
        await player.stop();
        await player.setReleaseMode(ReleaseMode.loop);
        await player.setVolume(1.0);
        await player.play(AssetSource(choroSound));
      },
    );
  }

  /// Para o som em loop (erro da charada).
  Future<void> stopLoop() async {
    await _guard(
      () => _loop(),
      () => _loopPlayer = AudioPlayer(),
      (p) => _loopPlayer = p,
      (player) => player.stop(),
    );
  }

  // ── Música de fundo (desafio final) ───────────────────────

  /// Toca a música de fundo do desafio final em LOOP (volume mais baixo).
  Future<void> playBackgroundMusic() async {
    await _guard(
      () => _background(),
      () => _backgroundPlayer = AudioPlayer(),
      (p) => _backgroundPlayer = p,
      (player) async {
        await player.stop();
        await player.setReleaseMode(ReleaseMode.loop);
        await player.setVolume(0.45);
        await player.play(AssetSource(bgDesafioSound));
      },
    );
  }

  /// Para a música de fundo.
  Future<void> stopBackgroundMusic() async {
    await _guard(
      () => _background(),
      () => _backgroundPlayer = AudioPlayer(),
      (p) => _backgroundPlayer = p,
      (player) => player.stop(),
    );
  }

  /// Para somente os efeitos (notificação, acerto, etc.) sem mexer no
  /// loop do erro nem na música de fundo.
  Future<void> stopEffects() async {
    await _guard(
      () => _effects(),
      () => _effectsPlayer = AudioPlayer(),
      (p) => _effectsPlayer = p,
      (player) => player.stop(),
    );
  }

  /// Para todos os sons (efeitos, loop de erro e música de fundo).
  ///
  /// Use no `dispose()` da tela — NUNCA [dispose].
  Future<void> stopAll() async {
    await stopLoop();
    await stopBackgroundMusic();

    await _guard(
      () => _effects(),
      () => _effectsPlayer = AudioPlayer(),
      (p) => _effectsPlayer = p,
      (player) => player.stop(),
    );
  }

  /// Libera os recursos definitivamente. Só deve ser chamado quando o app
  /// inteiro for encerrado — as telas NUNCA devem chamar isso.
  Future<void> dispose() async {
    try {
      await _effectsPlayer?.dispose();
    } catch (_) {}

    try {
      await _loopPlayer?.dispose();
    } catch (_) {}

    try {
      await _backgroundPlayer?.dispose();
    } catch (_) {}

    _effectsPlayer = null;
    _loopPlayer = null;
    _backgroundPlayer = null;
  }
}
