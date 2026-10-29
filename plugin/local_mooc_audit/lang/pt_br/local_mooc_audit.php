<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings em português (Brasil) para local_mooc_audit.
 *
 * @package    local_mooc_audit
 * @copyright  2026 Cefor/Ifes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Auditoria MOOC (prova de conceito)';
$string['mooc_audit:audit'] = 'Executar a auditoria técnica de um curso e ver o relatório (somente leitura)';
$string['mooc_audit:compare'] = 'Comparar dois cursos, origem e destino importado (somente leitura)';
$string['policyjson'] = 'Política institucional (JSON)';
$string['policyjson_desc'] = 'Objeto JSON que sobrescreve os parâmetros padrão da política (ano da reoferta, data de término, tentativas mínimas, nomes canônicos, blocos exigidos...). Vazio = padrões do plugin.';
$string['courseid'] = 'Id do curso';
$string['runaudit'] = 'Executar auditoria';
$string['privacy:metadata'] = 'O plugin de auditoria MOOC apenas lê configurações de cursos e não armazena dados pessoais.';

// Categories, states, severities and page sections.
$string['category_offer_enrol'] = 'Oferta e inscrição';
$string['category_institutional_resources'] = 'Recursos institucionais';
$string['category_assessment_grades'] = 'Avaliação e notas';
$string['category_completion_restrictions'] = 'Conclusão e restrições';
$string['category_certification'] = 'Certificação';
$string['category_content_files'] = 'Conteúdo e arquivos';
$string['category_structure_blocks'] = 'Estrutura, seções e blocos';
$string['category_plugins_environment'] = 'Plugins e ambiente';
$string['status_conforme'] = 'Conforme';
$string['status_nao_conforme'] = 'Não conforme';
$string['status_atencao'] = 'Atenção';
$string['status_nao_aplicavel'] = 'Não se aplica';
$string['status_nao_verificavel'] = 'Não verificável';
$string['status_erro'] = 'Erro na verificação';
$string['severity_blocking'] = 'Bloqueante';
$string['severity_high'] = 'Alta';
$string['severity_medium'] = 'Média';
$string['severity_low'] = 'Baixa';
$string['severity_info'] = 'Informativa';
$string['overall_bloqueada'] = 'Bloqueada';
$string['overall_requer_correcoes'] = 'Requer correções';
$string['overall_sem_inconformidades'] = 'Sem inconformidades';
$string['section_nonconformities'] = 'Inconformidades';
$string['section_warnings'] = 'Alertas';
$string['section_unverifiable'] = 'Não verificados ou não aplicáveis';
$string['section_unverifiable_nv'] = 'Não foi possível verificar';
$string['section_unverifiable_na'] = 'Não se aplica a esta sala';
$string['section_errors'] = 'Erro na verificação';
$string['section_conformant'] = 'Regras conformes';
$string['section_notices'] = 'Avisos';
$string['rerun'] = 'Executar novamente';
$string['jsonlink'] = 'JSON';
$string['auditmenu'] = 'Auditoria técnica';
$string['pagetitle'] = 'Auditoria técnica — {$a}';
$string['policy_invalid'] = 'Política institucional inválida ({$a}); os padrões do plugin foram usados.';
$string['policy_badtype'] = 'Política: a chave "{$a}" tem tipo inesperado; o padrão foi mantido.';
$string['site_completion_disabled'] = 'Acompanhamento de conclusão ou restrições de acesso desabilitados no site; regras de conclusão, restrições e certificação não puderam ser verificadas.';
$string['rule_error'] = 'Falha interna na regra: {$a}';

// Labels of the report page and of the CLI text output.
$string['label_blocks'] = 'Blocos';
$string['label_bysev'] = 'Inconformidades por gravidade';
$string['label_bystatus'] = 'Resultados por estado';
$string['label_category'] = 'Categoria';
$string['label_collection'] = 'Coleta';
$string['label_conformant_omitted'] = 'regras conformes omitidas';
$string['label_contract'] = 'contrato';
$string['label_engine'] = 'Auditor';
$string['label_enrols'] = 'Métodos de inscrição';
$string['label_evidence'] = 'Evidência';
$string['label_executed_at'] = 'Executada em';
$string['label_expected'] = 'Esperado';
$string['label_files'] = 'Arquivos';
$string['label_files_missing'] = 'sem conteúdo no armazenamento';
$string['label_found'] = 'Encontrado';
$string['label_grade_items'] = 'Itens de nota';
$string['label_guidance'] = 'Orientação';
$string['label_inventory'] = 'Inventário';
$string['label_local'] = 'Local';
$string['label_message'] = 'Mensagem';
$string['label_modules'] = 'Módulos';
$string['label_overall'] = 'Situação geral';
$string['label_period'] = 'Período';
$string['label_policy'] = 'Política';
$string['label_reason'] = 'Motivo';
$string['label_reoffer_year'] = 'Ano da reoferta';
$string['label_rule'] = 'Regra';
$string['label_rules'] = 'Regras';
$string['label_sections'] = 'Seções';
$string['label_status'] = 'Estado';
$string['label_time'] = 'tempo';
$string['label_visible'] = 'Visível';
$string['none_in_section'] = 'Nenhum.';

// Destinations of the correction links (one per locus).
$string['locus_module_edit'] = 'Configurações do módulo';
$string['locus_module_view'] = 'Página do módulo';
$string['locus_course_settings'] = 'Configurações do curso';
$string['locus_enrol'] = 'Métodos de inscrição';
$string['locus_gradebook'] = 'Configuração do livro de notas';
$string['locus_blocks'] = 'Página do curso (blocos)';
$string['locus_section'] = 'Página da seção';
$string['locus_import'] = 'Importar de outro curso';
$string['locus_none'] = 'Sem destino navegável';
$string['link_title'] = 'Abrir: {$a}';

// Rule titles and guidance (ids CL-nn / X-AREA-nn, keys rule_<id>_title, rule_<id>_guidance, rule_<id>_g_<name>).
$string['rule_cl04_guidance'] = 'Acrescentar o botão ao final do sumário (Editar configurações do curso > Descrição).';
$string['rule_cl04_title'] = 'Sumário contém botões institucionais';
$string['rule_cl05_guidance'] = 'Mover o curso para a categoria da oferta.';
$string['rule_cl05_title'] = 'Categoria do curso = categoria de inscrições abertas';
$string['rule_cl06_guidance'] = 'Ajustar a data de início da oferta.';
$string['rule_cl06_title'] = 'Data de início correta (01/01 do ano da reoferta)';
$string['rule_cl07_guidance'] = 'Ajustar a data de término conforme o calendário do Cefor.';
$string['rule_cl07_title'] = 'Data de término correta (31/12 do ano da reoferta)';
$string['rule_cl08_g_add'] = 'Adicionar o método Autoinscrição.';
$string['rule_cl08_g_guest'] = 'Confirmar se o acesso de visitante é desejado.';
$string['rule_cl08_guidance'] = 'Editar a Autoinscrição: habilitar, permitir novas inscrições e alinhar as datas com as do curso.';
$string['rule_cl08_title'] = 'Autoinscrição habilitada com período correto';
$string['rule_cl09_g_import'] = 'Importar o fórum da Sala Padrão. Verificação por nome canônico.';
$string['rule_cl09_guidance'] = 'Desabilitar a conclusão de atividade do fórum institucional.';
$string['rule_cl09_title'] = 'Fórum institucional "Espaço de interação" presente e sem conclusão';
$string['rule_cl11_g_completion'] = 'Sem conclusão automática a restrição do certificado nunca é satisfeita.';
$string['rule_cl11_g_import'] = 'Importar a Pesquisa de Perfil da Sala Padrão Oficial.';
$string['rule_cl11_guidance'] = 'Adicionar ao certificado a restrição de conclusão da Pesquisa de Perfil.';
$string['rule_cl11_title'] = 'Pesquisa de Perfil presente e exigida pelo certificado';
$string['rule_cl12_g_aggregation'] = 'Alterar a agregação da categoria do curso para Natural.';
$string['rule_cl12_g_onlygraded'] = 'Desmarcar "Excluir notas vazias": com ela ligada, a condição de 60 pontos do certificado é relativa só às atividades já avaliadas.';
$string['rule_cl12_g_subcats'] = 'Verificar agregação das subcategorias.';
$string['rule_cl12_guidance'] = 'Ajustar as notas máximas das atividades.';
$string['rule_cl12_title'] = 'Livro de notas: soma 100, agregação Natural, sem excluir vazias';
$string['rule_cl13_g_intro'] = 'Corrigir o enunciado ou a configuração.';
$string['rule_cl13_guidance'] = 'Editar configurações do questionário > Nota > Tentativas permitidas.';
$string['rule_cl13_title'] = 'Questionários com ao menos duas tentativas (e enunciado coerente)';
$string['rule_cl14_guidance'] = 'Definir a data de encerramento.';
$string['rule_cl14_title'] = 'Atividades avaliativas com data de encerramento';
$string['rule_cl16_guidance'] = 'Adicionar as restrições de conclusão faltantes.';
$string['rule_cl16_title'] = 'Avaliação do curso restrita à conclusão de todas as atividades com nota';
$string['rule_cl17_g_criteria'] = 'Rever o critério de conclusão conforme a tabela da política.';
$string['rule_cl17_guidance'] = 'Trocar para conclusão automática com o critério adequado ao tipo.';
$string['rule_cl17_title'] = 'Conclusão automática com critério adequado ao tipo';
$string['rule_cl18_g_repopulate'] = 'As entradas não vêm em backup/importação sem usuários: repovoar (importar XML do glossário) ou trocar a fonte dos jogos para o banco de questões.';
$string['rule_cl18_guidance'] = 'Remover o glossário (glossário colaborativo é proibido pela política).';
$string['rule_cl18_title'] = 'Glossário: proibido se colaborativo; vazio se fonte de jogos';
$string['rule_cl19_guidance'] = 'Desabilitar a conclusão no rótulo.';
$string['rule_cl19_title'] = 'Rótulos sem conclusão de atividade';
$string['rule_cl20_g_emptysection'] = 'Excluir ou ocultar a seção.';
$string['rule_cl20_g_genericname'] = 'Nomear a seção conforme o conteúdo.';
$string['rule_cl20_guidance'] = 'Marcar "Seções escondidas são completamente invisíveis" nas configurações do curso.';
$string['rule_cl20_title'] = 'Seções: ocultas invisíveis, sem seções vazias ou com nome genérico';
$string['rule_cl21_guidance'] = 'Adicionar o bloco HTML padrão (ou verificar o bloco de site correspondente).';
$string['rule_cl21_title'] = 'Blocos padrão presentes na página do curso';
$string['rule_cl22_guidance'] = 'Importar os componentes da Licença para capacitação (rótulo, pesquisa e declaração) da Sala Padrão.';
$string['rule_cl22_title'] = 'Licença para capacitação configurada';
$string['rule_cl23_g_courseitem'] = 'A condição de nota do certificado deve referenciar o Total do curso.';
$string['rule_cl23_g_import'] = 'Importar o certificado da Sala Padrão.';
$string['rule_cl23_guidance'] = 'Adicionar ao certificado as restrições: conclusão da Avaliação do curso e nota mínima no Total do curso, com operador "todas".';
$string['rule_cl23_title'] = 'Certificado condicionado à Avaliação do curso e à nota mínima no total';
$string['rule_xav01_guidance'] = 'Alinhar enunciado e nota máxima.';
$string['rule_xav01_title'] = 'Enunciado × configuração (pontos)';
$string['rule_xcert01_guidance'] = 'Verificação estática; a emissão real exige usuário de teste.';
$string['rule_xcert01_title'] = 'Alcançabilidade estática do certificado';
$string['rule_xcont01_g_reupload'] = 'Reenviar o arquivo. Na restauração do curso 670 isto ocorreu com aliases para "Arquivos do servidor" que não foram incluídos no backup.';
$string['rule_xcont01_guidance'] = 'Preencher ou remover o recurso.';
$string['rule_xcont01_title'] = 'Recursos vazios (página/rótulo sem conteúdo, questionário sem questões, arquivo sem arquivo)';
$string['rule_xcont04_guidance'] = 'Imagem/arquivo quebrado: reenviar no editor. Típico de aliases não restaurados ("unable to restore alias").';
$string['rule_xcont04_title'] = 'Referências @@PLUGINFILE@@ sem arquivo correspondente';
$string['rule_xdep01_guidance'] = 'Remover/recriar a restrição órfã.';
$string['rule_xdep01_title'] = 'Condições de acesso apontando para módulos inexistentes';
$string['rule_xdep02_guidance'] = 'Habilitar conclusão no módulo-alvo ou remover a condição; do contrário ela nunca será satisfeita.';
$string['rule_xdep02_title'] = 'Condição exige conclusão de módulo sem conclusão ou oculto';
$string['rule_xdep03_guidance'] = 'Recriar a condição de nota apontando para um item de nota existente.';
$string['rule_xdep03_title'] = 'Condições de nota apontando para itens inexistentes';
$string['rule_xdep04_guidance'] = 'Remover uma das condições para quebrar o ciclo.';
$string['rule_xdep04_title'] = 'Ciclos no grafo de dependências de conclusão';
$string['rule_xint02_guidance'] = 'Reenviar o arquivo. Se for alias (Arquivos do servidor), o original foi removido ou não foi copiado.';
$string['rule_xint02_title'] = 'Arquivos registrados sem conteúdo no armazenamento do Moodle';
$string['rule_xint03_guidance'] = 'Instalar o plugin no site ou remover seu uso no curso; condições de acesso de plugin ausente são ignoradas silenciosamente.';
$string['rule_xint03_title'] = 'Plugins usados pelo curso instalados no site';
$string['rule_xstr01_g_name'] = 'Dar um nome ao recurso.';
$string['rule_xstr01_guidance'] = 'Confirmar se deve permanecer oculto.';
$string['rule_xstr01_title'] = 'Módulos ocultos, invisíveis ao usuário ou com nome vazio';
