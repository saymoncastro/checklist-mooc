# Medição de escala do painel (A‑05)

Scripts **somente de laboratório**, fora do plugin. Rodam a partir da raiz do Moodle, num contêiner como o `moocaudit-rv-web`:

- `make_scale.php --n=3000`: cria N salas leves na categoria "Escala (A-05)", distribuídas em 10 subcategorias, com
  histórico clonado das execuções completas dos cursos A (id 2) e B (id 3), e um gerente sem papel de administrador
  (`gestor_escala`). Recusa rodar fora de host local com depuração de desenvolvedor.
- `measure_scale.php --repeat=3`: mede `dashboard::build` e a renderização para o administrador e para o gerente, em
  quatro cenários.

Saídas:
- `medicao-3000.txt` e `medicao-10000.txt`: os mesmos scripts, com 3.002 e com 10.002 cursos.
- `pagina-http.txt`: a página completa, lida no rodapé de desempenho do Moodle.

No arquivo de 10.000, a linha "uma subcategoria (~300)" corresponde a 1.000 salas por subcategoria, porque o rótulo
foi escrito para a carga de 3.000.
