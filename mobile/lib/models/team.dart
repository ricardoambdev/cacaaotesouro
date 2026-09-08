/// Modelo que representa uma equipe retornado pela API.
class Team {
  final int id;
  final String name;
  final String color;
  final String username;
  final int points;
  final String? status;
  final int? currentStep;

  const Team({
    required this.id,
    required this.name,
    required this.color,
    required this.username,
    required this.points,
    this.status,
    this.currentStep,
  });

  factory Team.fromJson(Map<String, dynamic> json) {
    return Team(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: (json['name'] as String?) ?? '',
      color: (json['color'] as String?) ?? '',
      username: (json['username'] as String?) ?? '',
      points: (json['points'] as num?)?.toInt() ?? 0,
      status: json['status'] as String?,
      currentStep: (json['current_step'] as num?)?.toInt(),
    );
  }

  Team copyWith({
    int? id,
    String? name,
    String? color,
    String? username,
    int? points,
    String? status,
    int? currentStep,
  }) {
    return Team(
      id: id ?? this.id,
      name: name ?? this.name,
      color: color ?? this.color,
      username: username ?? this.username,
      points: points ?? this.points,
      status: status ?? this.status,
      currentStep: currentStep ?? this.currentStep,
    );
  }
}
