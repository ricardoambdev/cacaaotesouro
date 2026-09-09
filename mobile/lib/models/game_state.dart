import 'team.dart';
import 'treasure.dart';

/// Mensagem enviada pelo admin para uma equipe.
class TeamMessage {
  final int id;
  final String message;
  final String createdAt;

  const TeamMessage({
    required this.id,
    required this.message,
    required this.createdAt,
  });

  factory TeamMessage.fromJson(Map<String, dynamic> json) {
    return TeamMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      message: (json['message'] as String?) ?? '',
      createdAt: (json['created_at'] as String?) ?? '',
    );
  }
}

/// Estado completo do jogo retornado por GET /api/team/state.
class GameState {
  final bool gameActive;
  final String story;
  final GameTreasure? currentTreasure;
  final bool finalAvailable;
  final String finalClue;
  final List<LeaderboardEntry> leaderboard;
  final Team team;
  final String? gameStatus;
  final String? gameStartDate;
  final String? gameStartTime;
  final String? gameEndTime;
  final List<TeamMessage> messages;

  const GameState({
    required this.gameActive,
    required this.story,
    this.currentTreasure,
    required this.finalAvailable,
    required this.finalClue,
    required this.leaderboard,
    required this.team,
    this.gameStatus,
    this.gameStartDate,
    this.gameStartTime,
    this.gameEndTime,
    this.messages = const [],
  });

  factory GameState.fromJson(Map<String, dynamic> json) {
    final teamData = json['team'] as Map<String, dynamic>;
    final leaderboardList = (json['leaderboard'] as List<dynamic>?)
            ?.map((e) => LeaderboardEntry.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final currentTreasureData = json['current_treasure'] as Map<String, dynamic>?;

    // gameActive pode vir como '1', 1, true, '0', 0 ou false.
    final gameActiveRaw = json['gameActive'];
    final gameActive = gameActiveRaw == true ||
        gameActiveRaw == 1 ||
        gameActiveRaw == '1';

    // Mensagens do admin
    final messagesList = (json['messages'] as List<dynamic>?)
            ?.map((e) => TeamMessage.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    return GameState(
      gameActive: gameActive,
      story: (json['story'] as String?) ?? '',
      currentTreasure: currentTreasureData != null
          ? GameTreasure.fromJson(currentTreasureData)
          : null,
      finalAvailable: json['final_available'] == true,
      finalClue: (json['final_clue'] as String?) ?? '',
      leaderboard: leaderboardList,
      team: Team.fromJson(teamData),
      gameStatus: json['game_status'] as String?,
      gameStartDate: json['game_start_date'] as String?,
      gameStartTime: json['game_start_time'] as String?,
      gameEndTime: json['game_end_time'] as String?,
      messages: messagesList,
    );
  }
}

/// Entrada no leaderboard.
class LeaderboardEntry {
  final String team;
  final int points;
  final String status;

  const LeaderboardEntry({
    required this.team,
    required this.points,
    required this.status,
  });

  factory LeaderboardEntry.fromJson(Map<String, dynamic> json) {
    // 'team' pode vir como string (nome) ou como objeto {id,name,color}.
    String teamName;
    final teamRaw = json['team'];
    if (teamRaw is Map) {
      teamName = (teamRaw['name'] as String?) ?? '';
    } else {
      teamName = (teamRaw as String?) ?? '';
    }

    return LeaderboardEntry(
      team: teamName,
      points: (json['points'] as num?)?.toInt() ?? 0,
      status: (json['status'] as String?) ?? '',
    );
  }
}

/// Resultado de um checkin.
class CheckinResult {
  final String message;
  final int assignedRiddle;
  final String riddle;
  final bool selfieQuestion;
  final bool selfieRequired;

  const CheckinResult({
    required this.message,
    required this.assignedRiddle,
    required this.riddle,
    required this.selfieQuestion,
    this.selfieRequired = false,
  });

  factory CheckinResult.fromJson(Map<String, dynamic> json) {
    return CheckinResult(
      message: (json['message'] as String?) ?? '',
      assignedRiddle: (json['assigned_riddle'] as num?)?.toInt() ?? 1,
      riddle: (json['riddle'] as String?) ?? '',
      selfieQuestion: json['selfie_question'] == true,
      selfieRequired: json['selfie_required'] == true,
    );
  }
}

/// Resultado de uma resposta (riddle ou final).
class AnswerResult {
  final bool correct;
  final String message;
  final int points;
  final GameTreasure? nextTreasure;
  final bool finalAvailable;
  final int attempts;

  const AnswerResult({
    required this.correct,
    required this.message,
    required this.points,
    this.nextTreasure,
    required this.finalAvailable,
    required this.attempts,
  });

  factory AnswerResult.fromJson(Map<String, dynamic> json) {
    final nextData = json['next'] as Map<String, dynamic>?;
    final treasureData = nextData?['treasure'] as Map<String, dynamic>?;

    return AnswerResult(
      correct: json['correct'] == true,
      message: (json['message'] as String?) ?? '',
      points: (json['points'] as num?)?.toInt() ?? 0,
      nextTreasure: treasureData != null ? GameTreasure.fromJson(treasureData) : null,
      finalAvailable: nextData?['final_available'] == true,
      attempts: (json['attempts'] as num?)?.toInt() ?? 1,
    );
  }
}

/// Estado do admin retornado por GET /api/admin/status.
class AdminStatus {
  final bool gameActive;
  final int? winnerTeamId;
  final List<AdminTeamStatus> teams;
  final List<dynamic>? treasuresProgress;

  const AdminStatus({
    required this.gameActive,
    this.winnerTeamId,
    required this.teams,
    this.treasuresProgress,
  });

  factory AdminStatus.fromJson(Map<String, dynamic> json) {
    final gameData = json['game'] as Map<String, dynamic>? ?? {};
    final teamsList = (json['teams'] as List<dynamic>?)
            ?.map((e) => AdminTeamStatus.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    // gameActive pode vir como '1', 1, true, '0', 0 ou false.
    final gameActiveRaw = gameData['gameActive'];
    final gameActive = gameActiveRaw == true ||
        gameActiveRaw == 1 ||
        gameActiveRaw == '1';

    // winnerTeamId pode vir como '' (vazio), '12' (string numérica),
    // 12 (num) ou null.
    int? winnerTeamId;
    final wRaw = gameData['winnerTeamId'];
    if (wRaw is num) {
      winnerTeamId = wRaw.toInt();
    } else if (wRaw is String && wRaw.trim() != '') {
      winnerTeamId = int.tryParse(wRaw.trim());
    }

    // treasures_progress chega como lista (Map em versões antigas).
    List<dynamic>? treasuresProgress;
    final tRaw = json['treasures_progress'];
    if (tRaw is List) {
      treasuresProgress = tRaw;
    } else if (tRaw is Map) {
      treasuresProgress = tRaw.values.toList();
    }

    return AdminStatus(
      gameActive: gameActive,
      winnerTeamId: winnerTeamId,
      teams: teamsList,
      treasuresProgress: treasuresProgress,
    );
  }
}

/// Status de uma equipe no painel admin.
class AdminTeamStatus {
  final int id;
  final String name;
  final String color;
  final int points;
  final String status;
  final String? finishedAt;
  final int foundCount;

  const AdminTeamStatus({
    required this.id,
    required this.name,
    required this.color,
    required this.points,
    required this.status,
    this.finishedAt,
    required this.foundCount,
  });

  factory AdminTeamStatus.fromJson(Map<String, dynamic> json) {
    return AdminTeamStatus(
      id: (json['id'] as num?)?.toInt() ?? 0,
      name: (json['name'] as String?) ?? '',
      color: (json['color'] as String?) ?? '#FFFFFF',
      points: (json['points'] as num?)?.toInt() ?? 0,
      status: (json['status'] as String?) ?? '',
      finishedAt: json['finished_at'] as String?,
      foundCount: (json['found_count'] as num?)?.toInt() ?? 0,
    );
  }
}
