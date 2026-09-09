import 'package:audioplayers/audioplayers.dart';

/// Serviço simples de sons do jogo (acerto/erro).
class SoundService {
  static final SoundService _instance = SoundService._();
  factory SoundService() => _instance;
  SoundService._();

  final AudioPlayer _player = AudioPlayer();

  /// Toca um asset de som. Para qualquer reprodução anterior antes de iniciar.
  Future<void> play(String asset) async {
    try {
      await _player.stop();
      await _player.play(AssetSource(asset));
    } catch (_) {
      // Falha silenciosa — som é acessório
    }
  }

  /// Toca o som de acerto.
  Future<void> playAcerto() => play('sounds/acerto.mp3');

  /// Toca o som de erro.
  Future<void> playChoro() => play('sounds/choro.mp3');

  /// Libera recursos.
  void dispose() {
    _player.dispose();
  }
}
