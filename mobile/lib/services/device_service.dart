import 'dart:math';
import 'package:shared_preferences/shared_preferences.dart';

/// Serviço para gerenciar o device_id persistente.
///
/// Gera um UUID v4 na primeira execução e salva em SharedPreferences.
/// Todas as chamadas de equipe enviam esse ID no header X-Device-Id.
class DeviceService {
  static const String _keyDeviceId = 'device_id';
  static const String _keyStoryVersion = 'story_version';

  // ── Singleton ──────────────────────────────────────────────
  static final DeviceService _instance = DeviceService._internal();
  factory DeviceService() => _instance;
  DeviceService._internal();

  String? _deviceId;

  /// Retorna o device_id. Gera um novo se não existir.
  Future<String> getDeviceId() async {
    if (_deviceId != null) return _deviceId!;

    final prefs = await SharedPreferences.getInstance();
    var id = prefs.getString(_keyDeviceId);

    if (id == null || id.isEmpty) {
      id = _generateUUID();
      await prefs.setString(_keyDeviceId, id);
    }

    _deviceId = id;
    return id;
  }

  /// Retorna a versão da história salva localmente (default 0).
  Future<int> getStoryVersion() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_keyStoryVersion) ?? 0;
  }

  /// Salva a versão da história exibida.
  Future<void> setStoryVersion(int version) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_keyStoryVersion, version);
  }

  /// Gera um UUID v4 sem pacotes externos.
  String _generateUUID() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40; // versão 4
    bytes[8] = (bytes[8] & 0x3f) | 0x80; // variante 1
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-'
        '${hex.substring(8, 12)}-'
        '${hex.substring(12, 16)}-'
        '${hex.substring(16, 20)}-'
        '${hex.substring(20)}';
  }
}
