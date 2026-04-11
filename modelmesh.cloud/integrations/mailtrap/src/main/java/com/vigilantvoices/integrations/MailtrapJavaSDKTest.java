package com.vigilantvoices.integrations;

import io.mailtrap.client.MailtrapClient;
import io.mailtrap.config.MailtrapConfig;
import io.mailtrap.factory.MailtrapClientFactory;
import io.mailtrap.model.request.emails.Address;
import io.mailtrap.model.request.emails.MailtrapMail;

import java.util.List;

public class MailtrapJavaSDKTest {

    public static void main(String[] args) {
        final String token = System.getenv("MAILTRAP_API_TOKEN");
        if (token == null || token.isBlank()) {
            System.err.println("MAILTRAP_API_TOKEN is not set.");
            System.err.println("Example: export MAILTRAP_API_TOKEN=your_token_here");
            return;
        }

        final MailtrapConfig config = new MailtrapConfig.Builder()
            .token(token)
            .build();

        final MailtrapClient client = MailtrapClientFactory.createMailtrapClient(config);

        final MailtrapMail mail = MailtrapMail.builder()
            .from(new Address("hello@demomailtrap.co", "Mailtrap Test"))
            .to(List.of(new Address("ravenell@modelsignal.cloud")))
            .subject("You are awesome!")
            .text("Congrats for sending test email with Mailtrap!")
            .category("Integration Test")
            .build();

        try {
            System.out.println(client.send(mail));
        } catch (Exception e) {
            System.out.println("Caught exception: " + e);
        }
    }
}
