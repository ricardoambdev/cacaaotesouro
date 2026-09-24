import 'package:flutter/material.dart';
import 'team.dart';
import 'treasure.dart';

/// Mensagem enviada pelo admin para uma equipe.
class TeamMessage {
  final int id;
  final String title;
  final String message;
  final String kind; // 'info' | 'success' | 'error'
  final String createdAt;

  const TeamMessage({
    required this.id,
    this.title = '',
    required this.message,
    this.kind = 'info',
    required this.createdAt,
  });

  /// Normaliza o kind para um dos três valores aceitos.
  static String _normalizeKind(String? raw) {
    if (raw == 'success' || raw == 'error') return raw!;
    return 'info';
  }

  factory TeamMessage.fromJson(Map<String, dynamic> json) {
    return TeamMessage(
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: (json['title'] as String?) ?? '',
      message: (json['message'] as String?) ?? '',
      kind: _normalizeKind(json['kind'] as String?),
      createdAt: (json['created_at'] as String?) ?? '',
    );
  }
}

/// Retorno de estilo visual por tipo de mensagem.
class MessageKindStyle {
  final Color color;
  final IconData icon;
  final String fallbackTitle;

  const MessageKindStyle({
    required this.color,
    required this.icon,
    required this.fallbackTitle,
  });

  /// Retorna o estilo visual correspondente ao [kind].
  static MessageKindStyle forKind(String kind) {
    switch (kind) {
      case 'success':
        return const MessageKindStyle(
          color: Colors.greenAccent,
          icon: Icons.check_circle,
          fallbackTitle: 'Sucesso',
        );
      case 'error':
        return const MessageKindStyle(
          color: Colors.redAccent,
          icon: Icons.error,
          fallbackTitle: 'Atenção',
        );
      default:
        return const MessageKindStyle(
          color: Color(0xFFF97316), // AppColors.gold
          icon: Icons.info,
          fallbackTitle: 'Informação',
        );
    }
  }

  /// Título efetivo: usa o título da mensagem se não estiver vazio, senão o
  /// fallback do tipo.
  String effectiveTitle(String? messageTitle) {
    final t = messageTitle?.trim() ?? '';
    return t.isNotEmpty ? t : fallbackTitle;
  }
}

/// Estado completo do jogo retornado por GET /api/team/state.
class GameState {
  final bool gameActive;
  final String story;
  final int storyVersion;
  /// Regras da gincana (texto HTML) — aparece na aba "Regras".
  final String rules;
  final GameTreasure? currentTreasure;
  final bool finalAvailable;
  final String finalClue;
  final int finalCorrectPoints;
  final int finalWrongPenalty;
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
    this.storyVersion = 0,
    this.rules = '',
    this.currentTreasure,
    required this.finalAvailable,
    required this.finalClue,
    this.finalCorrectPoints = 100,
    this.finalWrongPenalty = 20,
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
      storyVersion: (json['story_version'] as num?)?.toInt() ?? 0,
      rules: (json['rules'] as String?) ?? '',
      currentTreasure: currentTreasureData != null
          ? GameTreasure.fromJson(currentTreasureData)
          : null,
      finalAvailable: json['final_available'] == true,
      finalClue: (json['final_clue'] as String?) ?? '',
      finalCorrectPoints: (json['final_correct_points'] as num?)?.toInt() ?? 100,
      finalWrongPenalty: (json['final_wrong_penalty'] as num?)?.toInt() ?? 20,
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
  final int answerLength;

  const CheckinResult({
    required this.message,
    required this.assignedRiddle,
    required this.riddle,
    required this.selfieQuestion,
    this.selfieRequired = false,
    this.answerLength = 6,
  });

  factory CheckinResult.fromJson(Map<String, dynamic> json) {
    final rawLen = (json['answer_length'] as num?)?.toInt() ?? 0;
    return CheckinResult(
      message: (json['message'] as String?) ?? '',
      assignedRiddle: (json['assigned_riddle'] as num?)?.toInt() ?? 1,
      riddle: (json['riddle'] as String?) ?? '',
      selfieQuestion: json['selfie_question'] == true,
      selfieRequired: json['selfie_required'] == true,
      answerLength: rawLen >= 1 && rawLen <= 8 ? rawLen : 6,
    );
  }
}

/// Item de breakdown de pontos (ex: "Resposta correta": +20).
class BreakdownItem {
  final String label;
  final int points;

  const BreakdownItem({required this.label, required this.points});

  factory BreakdownItem.fromJson(Map<String, dynamic> json) {
    return BreakdownItem(
      label: (json['label'] as String?) ?? '',
      points: (json['points'] as num?)?.toInt() ?? 0,
    );
  }
}

/// Resultado de uma resposta (riddle ou final).
class AnswerResult {
  final bool correct;
  final String message;
  final int points;
  final int delta;
  final int totalEarned;
  final List<BreakdownItem> breakdown;
  final GameTreasure? nextTreasure;
  final bool finalAvailable;
  final int attempts;

  const AnswerResult({
    required this.correct,
    required this.message,
    required this.points,
    this.delta = 0,
    this.totalEarned = 0,
    this.breakdown = const [],
    this.nextTreasure,
    required this.finalAvailable,
    required this.attempts,
  });

  factory AnswerResult.fromJson(Map<String, dynamic> json) {
    final nextData = json['next'] as Map<String, dynamic>?;
    final treasureData = nextData?['treasure'] as Map<String, dynamic>?;

    // Parse breakdown; fallback para lista vazia se ausente.
    final breakdownList = (json['breakdown'] as List<dynamic>?)
            ?.map((e) => BreakdownItem.fromJson(e as Map<String, dynamic>))
            .toList() ??
        [];

    final delta = (json['delta'] as num?)?.toInt() ?? 0;

    return AnswerResult(
      correct: json['correct'] == true,
      message: (json['message'] as String?) ?? '',
      points: (json['points'] as num?)?.toInt() ?? 0,
      delta: delta,
      totalEarned: (json['total_earned'] as num?)?.toInt() ?? delta,
      breakdown: breakdownList,
      nextTreasure: treasureData != null ? GameTreasure.fromJson(treasureData) : null,
      finalAvailable: nextData?['final_available'] == true,
      attempts: (json['attempts'] as num?)?.toInt() ?? 1,
    );
  }
}

/// Estado do admin retornado por GET /api/admin/status.
class AdminStatus {
  final bool gameActive;
  final String gameStatus;
  final int? winnerTeamId;
  final List<AdminTeamStatus> teams;
  final List<dynamic>? treasuresProgress;
  /// `null` quando o servidor NÃO retorna o campo (código antigo).
  /// `''` quando o campo existe mas está vazio.
  /// Conteúdo HTML quando há história.
  final String? story;
  final int storyVersion;
  /// Regras da gincana (texto HTML) — null quando o servidor não envia.
  final String? rules;

  const AdminStatus({
    required this.gameActive,
    this.gameStatus = 'playing',
    this.winnerTeamId,
    required this.teams,
    this.treasuresProgress,
    this.story,
    this.storyVersion = 0,
    this.rules,
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

    // gameStatus (novo) pode vir como 'playing' | 'paused' | 'finished'.
    final gameStatus =
        (gameData['gameStatus'] as String?) ?? (gameActive ? 'playing' : 'paused');

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
      gameStatus: gameStatus,
      winnerTeamId: winnerTeamId,
      teams: teamsList,
      treasuresProgress: treasuresProgress,
      story: json['story'] as String?,
      storyVersion: (json['story_version'] as num?)?.toInt() ?? 0,
      rules: json['rules'] as String?,
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
