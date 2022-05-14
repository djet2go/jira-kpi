# Быстрые ссылки

# KPI reporter

![KPI screen description](https://github.com/djet2go/jira-kpi/raw/main/kpi-description.png)


# Структура файлов

- `kpi.php` - Сам скрипт, который счиатает статистику
- `kpi-builder.php` - Морда чтобы задать jql-запрос и количество рабочих деней по людям
- `result/stat.ini` - Статистика по митингам
- `Unirest/` - Либка для REST-запросов
- `jiraUnirest.php` - Либка для работы с Jira

## Построение отчета по KPI

Для построения отчета необходимо вызвать файл kpi.php с GET-параметрами запроса:

| Параметр                    | Обязательный | Описание                                                                                                      |
|-----------------------------|--------------|---------------------------------------------------------------------------------------------------------------|
| `dateFrom`                  | -            | Дата начала выборки, пример - `2021-11-01`                                                                    |
| `dateTo`                    | -            | Дата окончания выборки, пример - `2021-11-15`                                                                 |
| `jql`                       | -            | jql[^1]-запрос (см. пример ниже). При использовании `jql` никаких других параметров задавать не нужно         |
| `emp[{JIRA-USER-FULLNAME}]` | -            | Количество рабочих дней за период, пример `emp[Nikita+Prodman]=5`; если не задано будет взято из `config.ini` |

Примеры запросов:
```raw
http://localhost/kpi.php?dateFrom=2021-11-01&dateTo=2021-11-15
```
```raw
http://localhost/kpi.php?jql=(%22Epic%20Link%22%20=%20VC-2550%20OR%20%22Epic%20Link%22%20=%20VC-2479)%20and%20status%20in%20(%22Ready%20for%20testing%22,%20Done)
```
```raw
http://localhost/kpi.php?jql=project+%3D+vc+AND+status+in+%28%22Ready+for+testing%22%2C+Testing%2C+Done%29+and+type+not+in+%28Epic%2C+Story%29+AND+worklogDate+%3E%3D+startOfWeek%28-1%29+AND+worklogDate+%3C%3D+endOfWeek%28-1%29&emp%5BMykhaylo+Yuminov%5D=4&emp%5BPavel+Vlasenko%5D=5&emp%5BAlexandr%5D=5&emp%5BХруслов+Дмитрий%5D=5&emp%5BAlina+Bashlykova%5D=5&emp%5BVasyl+Naumenko%5D=5&emp%5BPolovynka+Ivan%5D=5&emp%5BAndrii+Prykhodko%5D=5&emp%5BVidieiev+Dmytro%5D=3&emp%5BНикита+Ельцов%5D=5&emp%5BTatiana+Stepanenko%5D=5
```

# Конфигурация

Файл конфигурации - `config.ini`

## Запрос по умолчанию для сбора KPI
В поле `query -> default_kpi` можно указать запрос по умолчанию по сбору статистики для подсчета KPI:

```ini
[query]
default_kpi = 'project = pr AND status in ("Ready for testing", Done) and type not in (Epic, Story) AND statusCategoryChangedDate >= startOfMonth() AND statusCategoryChangedDate <= endOfMonth()'
```
В приведенном пример делается выборка задач от начала текущего месяца до конца текущего месяца. Учитываются только задачи с типом `Task` и `Bug` в статусах "Ready for testing" и "Done"

## Конфигурация доступа к API Jira

Указать хост, email и API-key для доступа можно в блоке `connect`:
```ini
[connect]
email = "guest@example.com"
token = "example_token"
host = "https://example.atlassian.net"
```
Подробнее о получении Jira API Key можно узнать [здесь](https://support.atlassian.com/atlassian-account/docs/manage-api-tokens-for-your-atlassian-account/)

[^1]: [Jira Query Language](https://www.atlassian.com/ru/software/jira/guides/expand-jira/jql)