/// Lista negra de nomes (palavrões, xingamentos e apelidos proibidos).
///
/// A lista é definida pela organização no painel e chega ao app junto do
/// login. Aqui ela serve para **avisar na hora** — quem decide de verdade é
/// o servidor (que também recusa).
class NameBlocklist {
  NameBlocklist._();

  /// Palavras já normalizadas (minúsculas, sem acento).
  static List<String> _words = [];

  /// Lista BRANCA: nomes liberados mesmo se "esconderem" palavra proibida.
  static List<String> _whitelist = [];

  /// Guarda a lista recebida do servidor (ex.: no login).
  static void setWords(List<dynamic>? words) {
    _words = (words ?? [])
        .map((w) => normalize(w.toString()))
        .where((w) => w.isNotEmpty)
        .toSet()
        .toList();
  }

  /// Guarda a lista branca recebida do servidor (ex.: no login).
  static void setWhitelist(List<dynamic>? words) {
    _whitelist = (words ?? [])
        .map((w) => normalize(w.toString()))
        .where((w) => w.isNotEmpty)
        .toSet()
        .toList();
  }

  static List<String> get words => List.unmodifiable(_words);

  static List<String> get whitelist => List.unmodifiable(_whitelist);

  /// Tira do nome os trechos liberados (lista branca).
  ///
  /// Assim "João Matarazzo" passa, mas "Matarazzo merda" continua bloqueado.
  static String _withoutWhitelisted(String normalized) {
    var result = normalized;

    for (final allowed in _whitelist) {
      if (allowed.isNotEmpty) {
        result = result.replaceAll(allowed, ' ');
      }
    }

    return result;
  }

  /// O nome está bloqueado?
  static bool isBlocked(String name) => blockedWord(name) != '';

  /// Palavra bloqueada encontrada ('' quando o nome está livre).
  static String blockedWord(String name) {
    final normalized = _withoutWhitelisted(normalize(name));

    if (normalized.isEmpty) return '';

    final tokens = normalized.split(RegExp(r'[^a-z0-9]+'));

    for (final word in _words) {
      // Palavra exata (ex.: "cu" só bloqueia o nome "cu").
      if (tokens.contains(word)) return word;

      // Palavra longa escondida no meio (ex.: "viado123").
      if (word.length >= 4 && normalized.contains(word)) return word;
    }

    return '';
  }

  /// Minúsculas e sem acentos, para comparar.
  static String normalize(String text) {
    var t = text.trim().toLowerCase();

    const from = 'áàãâäéèêëíìîïóòõôöúùûüçñ';
    const to = 'aaaaaeeeeiiiiooooouuuucn';

    final buffer = StringBuffer();

    for (final rune in t.runes) {
      final ch = String.fromCharCode(rune);
      final idx = from.indexOf(ch);
      buffer.write(idx >= 0 ? to[idx] : ch);
    }

    return buffer.toString();
  }
}
