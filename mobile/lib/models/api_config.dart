/// Configuração pública retornada por GET /api/config.
class ApiConfig {
  final String appName;
  final String apiBaseUrl;
  final bool devMode;

  const ApiConfig({
    required this.appName,
    required this.apiBaseUrl,
    required this.devMode,
  });

  factory ApiConfig.fromJson(Map<String, dynamic> json) {
    return ApiConfig(
      appName: (json['appName'] as String?) ?? 'Caça ao Tesouro',
      apiBaseUrl: (json['apiBaseUrl'] as String?) ?? '',
      devMode: json['devMode'] == true,
    );
  }
}
