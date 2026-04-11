# Mailtrap Java Integration

This module sends a test email using the Mailtrap Java SDK.

## Prerequisites

- Java 17+
- Maven
- Mailtrap API token

## Configure token

```bash
export MAILTRAP_API_TOKEN=your_mailtrap_token
```

## Run

```bash
cd /var/www/modelmesh.cloud/integrations/mailtrap
mvn -q compile exec:java
```

## Source file

- `src/main/java/com/vigilantvoices/integrations/MailtrapJavaSDKTest.java`
