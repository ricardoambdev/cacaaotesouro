/// Lista negra de nomes (palavrões, xingamentos e apelidos proibidos).
///
/// A lista é definida pela organização no painel e chega ao app junto do
/// login. Aqui ela serve para **avisar na hora** — quem decide de verdade é
/// o servidor (que também recusa).
class NameBlocklist {
  NameBlocklist._();

  /// Palavras já normalizadas (minúsculas, sem acento).
  static List<String> _words = [];

  /// Guarda a lista recebida do servidor (ex.: no login).
  static void setWords(List<dynamic>? words) {
    _words = (words ?? [])
        .map((w) => normalize(w.toString()))
        .where((w) => w.isNotEmpty)
        .toSet()
        .toList();
  }

  static List<String> get words => List.unmodifiable(_words);

  /// O nome está bloqueado?
  static bool isBlocked(String name) => blockedWord(name) != '';

  /// Palavra bloqueada encontrada ('' quando o nome está livre).
  static String blockedWord(String name) {
    final normalized = normalize(name);

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
