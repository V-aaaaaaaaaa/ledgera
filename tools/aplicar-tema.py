#!/usr/bin/env python3
"""
LEDGERA · Pasa los colores verdes fijos de un CSS a las variables del tema (public/css/tema.css).

Uso:   python tools/aplicar-tema.py public/css/mi-modulo.css [otro.css ...]

- Es seguro repetirlo: lo que ya usa variables no se toca.
- Las líneas que DEFINEN variables (empiezan con --) no se tocan.
- Los colores de estado (rojo de error, ámbar de aviso) se dejan como están.
"""
import re
import sys

A, AO, CL, SU, BO, FP, TS = ('var(--acento)', 'var(--acento-oscuro)', 'var(--claro)', 'var(--suave)',
                             'var(--borde)', 'var(--fondo-pagina)', 'var(--texto-suave)')

MAPA = {
    # acento (botones, bordes activos)
    '#1a5a1a': A, '#1a6a1a': A, '#145a32': A,
    # oscuro (encabezados, texto fuerte)
    '#1a3d1a': AO, '#0a3d0a': AO, '#0d3d0d': AO, '#0f3d2e': AO,
    '#1a5a45': 'hsl(var(--h), 55%, 24%)',
    # tintes claros
    '#e8f0e8': CL, '#e8f5ee': CL,
    '#f6faf6': SU, '#eef4ee': SU, '#eef6ee': SU, '#f0f7f0': SU, '#fafdfa': SU,
    '#cfe0cf': BO, '#e0ede0': BO, '#d4e4d4': BO, '#b8d4b8': BO, '#c9dcc9': BO, '#c0d8c0': BO,
    '#f1f6f1': FP, '#f5f7f6': FP,
    # texto secundario
    '#4a6a4a': TS, '#6a8a6a': TS, '#5a7a5a': TS, '#3a5a3a': TS, '#2a3d2a': TS,
    '#8aa88a': TS, '#a0b8a0': TS, '#7a947a': TS,
    # brillos verdes de los dashboards
    '#27ae60': 'hsl(var(--h), 62%, 46%)',
    '#2ecc71': 'hsl(var(--h), 70%, 52%)',
    '#4ade80': 'hsl(var(--h), 75%, 62%)',
}

RGBA = [
    (re.compile(r'rgba\(\s*15\s*,\s*61\s*,\s*46\s*,\s*([0-9.]+)\s*\)'), r'hsla(var(--h), 55%, 16%, \1)'),
    (re.compile(r'rgba\(\s*39\s*,\s*174\s*,\s*96\s*,\s*([0-9.]+)\s*\)'), r'hsla(var(--h), 65%, 45%, \1)'),
    (re.compile(r'rgba\(\s*46\s*,\s*204\s*,\s*113\s*,\s*([0-9.]+)\s*\)'), r'hsla(var(--h), 70%, 52%, \1)'),
    (re.compile(r'rgba\(\s*26\s*,\s*90\s*,\s*26\s*,\s*([0-9.]+)\s*\)'), r'hsla(var(--h), 55%, 36%, \1)'),
]
HEX = re.compile(r'#[0-9a-fA-F]{6}\b')


def convertir_linea(linea):
    if linea.lstrip().startswith('--'):          # definición de variable: no se toca
        return linea, 0
    cambios = 0
    nueva = HEX.sub(lambda m: MAPA.get(m.group(0).lower(), m.group(0)), linea)
    cambios += int(nueva != linea)
    for patron, reemplazo in RGBA:
        despues = patron.sub(reemplazo, nueva)
        cambios += int(despues != nueva)
        nueva = despues
    return nueva, cambios


def main(rutas):
    for ruta in rutas:
        with open(ruta, encoding='utf-8') as f:
            lineas = f.read().split('\n')
        total, salida = 0, []
        for linea in lineas:
            nueva, n = convertir_linea(linea)
            total += n
            salida.append(nueva)
        with open(ruta, 'w', encoding='utf-8') as f:
            f.write('\n'.join(salida))
        print(f'{ruta}: {total} líneas cambiadas')


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    main(sys.argv[1:])
