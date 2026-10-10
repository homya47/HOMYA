"""Closed native dump dialect for OP-07. Pure code: no connection or credentials."""
import hashlib
import re

TABLES = ('archive_lots', 'archive_categories', 'archive_products', 'archive_translations', 'migrations')
PACKET = 16777216


def require(condition, code):
    if not condition:
        raise RuntimeError(code)


def tokens(sql):
    """Tokenize quoted data independently of SQL keywords. Unknown syntax fails closed."""
    out, i = [], 0
    while i < len(sql):
        char = sql[i]
        if char.isspace():
            i += 1
            continue
        if char in "'`":
            quote, value = char, []
            i += 1
            while i < len(sql):
                char = sql[i]
                if char == quote:
                    if i + 1 < len(sql) and sql[i + 1] == quote:
                        value.append(quote)
                        i += 2
                        continue
                    i += 1
                    break
                if char == '\\' and quote == "'":
                    i += 1
                    require(i < len(sql), 'DUMP_UNFINISHED_ESCAPE')
                    mapping = {'0': '\0', 'n': '\n', 'r': '\r', 't': '\t', 'b': '\b', 'Z': '\x1a',
                               '\\': '\\', "'": "'", '"': '"'}
                    require(sql[i] in mapping, 'DUMP_UNKNOWN_ESCAPE')
                    value.append(mapping[sql[i]])
                else:
                    value.append(char)
                i += 1
            else:
                raise RuntimeError('DUMP_UNFINISHED_QUOTE')
            out.append(('id' if quote == '`' else 'str', ''.join(value)))
            continue
        match = re.match(r'0x[0-9a-fA-F]+|(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?', sql[i:])
        if match:
            value = match.group()
            out.append(('hex' if value.startswith('0x') else 'num', value))
            i += len(value)
            continue
        match = re.match(r'[A-Za-z_][A-Za-z_0-9]*', sql[i:])
        if match:
            out.append(('word', match.group().upper()))
            i += len(match.group())
            continue
        require(char in '(),=+-@.*<>!|&', 'DUMP_UNKNOWN_TOKEN')
        out.append(('sym', char))
        i += 1
    return out


def statements(stream):
    """Bounded incremental split; semicolons in values and executable comments are data."""
    buf, quote, escaped, comment, star, slash = [], None, False, False, False, False
    size = 0
    for chunk in iter(lambda: stream.read(65536), ''):
        for char in chunk:
            buf.append(char)
            size += len(char.encode('utf8'))
            require(size < PACKET - 1, 'DUMP_STATEMENT_PACKET')
            if comment:
                if star and char == '/':
                    comment, star = False, False
                else:
                    star = char == '*'
                continue
            if quote:
                if escaped:
                    escaped = False
                elif char == '\\' and quote == "'":
                    escaped = True
                elif char == quote:
                    quote = None
                continue
            if slash and char == '*':
                comment, slash = True, False
                continue
            slash = char == '/'
            if char in "'`":
                quote = char
            elif char == ';':
                sql = ''.join(buf[:-1]).strip()
                require(bool(sql), 'DUMP_EMPTY_STATEMENT')
                yield sql, size
                buf, size = [], 0
    require(not quote and not comment and not escaped, 'DUMP_TRUNCATED')
    require(not ''.join(buf).strip(), 'DUMP_UNTERMINATED')


SET_TEXT = (
    'SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT',
    'SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS',
    'SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION',
    'SET NAMES utf8mb4',
    'SET @OLD_TIME_ZONE=@@TIME_ZONE', "SET TIME_ZONE='+00:00'",
    'SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0',
    'SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0',
    "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'",
    'SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0',
    'SET @saved_cs_client=@@character_set_client', 'SET character_set_client=utf8mb4',
    'SET character_set_client=utf8',
    'SET character_set_client=@saved_cs_client',
    'SET TIME_ZONE=@OLD_TIME_ZONE', 'SET SQL_MODE=@OLD_SQL_MODE',
    'SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS', 'SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS',
    'SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT',
    'SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS',
    'SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION', 'SET SQL_NOTES=@OLD_SQL_NOTES',
)
ALLOWED_SETS = {tuple(tokens(s)) for s in SET_TEXT}


def ddl_sql(table, value):
    prefix = table + '\t'
    require(value.startswith(prefix), 'DUMP_DDL_PROOF')
    return value[len(prefix):]


def ddl_columns(ddl):
    return tuple(re.findall(r'^  `([a-z_]+)` ', ddl, re.M))


def insert_table(parsed, columns):
    require(parsed[:2] == [('word', 'INSERT'), ('word', 'INTO')], 'DUMP_INSTRUCTION')
    require(len(parsed) > 8 and parsed[2][0] == 'id' and parsed[2][1] in TABLES, 'DUMP_TABLE')
    table, i, names = parsed[2][1], 3, []
    require(parsed[i] == ('sym', '('), 'DUMP_INSERT_COLUMNS')
    i += 1
    while i < len(parsed):
        require(parsed[i][0] == 'id', 'DUMP_INSERT_COLUMNS')
        names.append(parsed[i][1]); i += 1
        require(i < len(parsed), 'DUMP_INSERT_COLUMNS')
        if parsed[i] == ('sym', ')'):
            i += 1
            break
        require(parsed[i] == ('sym', ','), 'DUMP_INSERT_COLUMNS')
        i += 1
    require(tuple(names) == columns[table], 'DUMP_INSERT_COLUMNS')
    require(parsed[i:i+2] == [('word', 'VALUES'), ('sym', '(')], 'DUMP_INSERT_VALUES')
    i += 2
    count = 0
    while i < len(parsed):
        if parsed[i] in [('sym', '+'), ('sym', '-')]:
            i += 1
            require(i < len(parsed) and parsed[i][0] == 'num', 'DUMP_INSERT_LITERAL')
        elif parsed[i] == ('word', '_BINARY'):
            i += 1
            require(i < len(parsed) and parsed[i][0] == 'str', 'DUMP_INSERT_LITERAL')
        require(parsed[i][0] in ('str', 'num', 'hex') or parsed[i] == ('word', 'NULL'), 'DUMP_INSERT_LITERAL')
        if parsed[i][0] == 'hex':
            require(len(parsed[i][1][2:]) % 2 == 0, 'DUMP_HEX_LITERAL')
        count += 1; i += 1
        require(i < len(parsed), 'DUMP_INSERT_VALUES')
        if parsed[i] == ('sym', ')'):
            i += 1
            break
        require(parsed[i] == ('sym', ','), 'DUMP_INSERT_VALUES')
        i += 1
    require(i == len(parsed) and parsed[-1] == ('sym', ')') and count == len(names), 'DUMP_INSERT_VALUES')
    return table


def validate_dump(stream, physical_ddl, counts):
    require(set(physical_ddl) == set(TABLES) and set(counts) == set(TABLES), 'DUMP_EXPECTATIONS')
    expected = {t: tokens(ddl_sql(t, physical_ddl[t])) for t in TABLES}
    columns = {t: ddl_columns(ddl_sql(t, physical_ddl[t])) for t in TABLES}
    require(all(columns.values()), 'DUMP_DDL_COLUMNS')
    created, actual, maximum, total = set(), dict.fromkeys(TABLES, 0), 0, 0
    saved_client = tuple(tokens('SET @saved_cs_client=@@character_set_client'))
    ddl_client = tuple(tokens('SET character_set_client=utf8'))
    restored_client = tuple(tokens('SET character_set_client=@saved_cs_client'))
    previous_set, ddl_encoding = None, None
    for sql, size in statements(stream):
        require(not re.search(r'(?im)^\s*(?:DELIMITER\b|\\C\s)', sql), 'DUMP_CLIENT_COMMAND')
        maximum = max(maximum, size); total += 1
        if sql.startswith('/*!'):
            match = re.fullmatch(r'/\*!(40101|40103|40014|40111)\s+(.+?)\s*\*/', sql, re.S)
            require(match is not None and tuple(tokens(match[2])) in ALLOWED_SETS, 'DUMP_SESSION_STATEMENT')
            session = tuple(tokens(match[2]))
            if ddl_encoding == 'restore':
                require(match[1] == '40101' and session == restored_client, 'DUMP_DDL_CHARSET_SEQUENCE')
                ddl_encoding = None
            else:
                require(ddl_encoding is None, 'DUMP_DDL_CHARSET_SEQUENCE')
                if session == ddl_client:
                    # XAMPP 10.4.28 uses utf8 only for the following exact ASCII DDL.
                    # Data must follow the restore, retaining the saved utf8mb4 client.
                    require(match[1] == '40101' and previous_set == ('40101', saved_client), 'DUMP_DDL_CHARSET_SEQUENCE')
                    ddl_encoding = 'create'
            previous_set = (match[1], session)
            continue
        parsed = tokens(sql)
        require(ddl_encoding != 'restore', 'DUMP_DDL_CHARSET_SEQUENCE')
        previous_set = None
        if parsed[:2] == [('word', 'CREATE'), ('word', 'TABLE')]:
            require(len(parsed) > 2 and parsed[2][0] == 'id' and parsed[2][1] in TABLES, 'DUMP_TABLE')
            table = parsed[2][1]
            require(table not in created and parsed == expected[table], 'DUMP_DDL_MISMATCH')
            created.add(table)
            if ddl_encoding == 'create':
                ddl_encoding = 'restore'
        else:
            require(ddl_encoding is None, 'DUMP_DDL_CHARSET_SEQUENCE')
            table = insert_table(parsed, columns)
            require(table in created, 'DUMP_INSERT_BEFORE_CREATE')
            actual[table] += 1
    require(ddl_encoding is None, 'DUMP_DDL_CHARSET_SEQUENCE')
    require(created == set(TABLES) and actual == counts, 'DUMP_COMPLETENESS')
    return {'tables': list(TABLES), 'counts': actual, 'statement_count': total,
            'max_statement_bytes': maximum, 'packet_bytes': PACKET}


def canonical_row(values):
    """NULL and exact server byte strings; numeric/date representations are fixed by schema/client."""
    result = bytearray()
    for value in values:
        if value is None:
            result.extend(b'N;')
        else:
            require(isinstance(value, bytes), 'ROW_BYTES_REQUIRED')
            result.extend(b'B' + str(len(value)).encode('ascii') + b':' + value + b';')
    return bytes(result)


def hash_rows(rows):
    hasher = hashlib.sha256()
    count = 0
    for row in rows:
        encoded = canonical_row(row)
        hasher.update(str(len(encoded)).encode('ascii') + b':' + encoded)
        count += 1
    return {'rows': count, 'sha256': hasher.hexdigest()}
