/// Modelo que representa um tesouro no fluxo do jogo.
///
/// Retornado pelo teamState (current_treasure) e pelo admin/treasures.
class GameTreasure {
  final int id;
  final String name;
  final String? clue;
  final bool hasLocation;

  // Campos do admin
  final String? code;
  final double? lat;
  final double? lng;
  final bool hasCoord;
  final bool active;
  final String? qrContent;

  const GameTreasure({
    required this.id,
    required this.name,
    this.clue,
    this.hasLocation = false,
    this.code,
    this.lat,
    this.lng,
    this.hasCoord = false,
    this.active = true,
    this.qrContent,
  });

  factory GameTreasure.fromJson(Map<String, dynamic> json) {
    return GameTreasure(
      id: json['id'] as int,
      name: (json['name'] as String?) ?? '',
      clue: json['clue'] as String?,
      hasLocation: json['has_location'] == true,
      code: json['code'] as String?,
      lat: (json['lat'] != null) ? (json['lat'] as num).toDouble() : null,
      lng: (json['lng'] != null) ? (json['lng'] as num).toDouble() : null,
      hasCoord: json['has_coord'] == true,
      active: json['active'] != false,
      qrContent: json['qr_content'] as String?,
    );
  }

  String get coordinatesDisplay {
    if (lat != null && lng != null) {
      return '${lat!.toStringAsFixed(6)}, ${lng!.toStringAsFixed(6)}';
    }
    return 'Não definida';
  }
}
