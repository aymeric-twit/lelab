                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 32px; background-color: #f8f9fa; text-align: center; border-top: 1px solid #e2e8f0;">
                            <?php if (!empty($_estDesabonnable) && !empty($_lienDesabonnement)): ?>
                            <p style="color: #999; font-size: 11px; margin: 0 0 8px;">
                                <a href="<?= htmlspecialchars($_lienDesabonnement) ?>" style="color: #66b2b2; text-decoration: underline;">G&eacute;rer mes pr&eacute;f&eacute;rences de notification</a>
                                &nbsp;&bull;&nbsp;
                                <a href="<?= htmlspecialchars($_lienDesabonnement) ?>&action=tout" style="color: #999; text-decoration: underline;">Se d&eacute;sabonner</a>
                            </p>
                            <?php endif; ?>
                            <p style="color: #999; font-size: 11px; margin: 0 0 4px;">
                                Le Lab Y'A PAS DE QUOI &mdash; votre bo&icirc;te &agrave; outils SEO
                            </p>
                            <p style="color: #bbb; font-size: 10px; margin: 0;">
                                <?php if (!empty($_lienConfidentialite)): ?>
                                <a href="<?= htmlspecialchars($_lienConfidentialite) ?>" style="color: #bbb; text-decoration: underline;">Politique de confidentialit&eacute;</a>
                                <?php endif; ?>
                                <?php if (!empty($_lienConfidentialite) && !empty($_lienMentions)): ?>
                                &nbsp;&bull;&nbsp;
                                <?php endif; ?>
                                <?php if (!empty($_lienMentions)): ?>
                                <a href="<?= htmlspecialchars($_lienMentions) ?>" style="color: #bbb; text-decoration: underline;">Mentions l&eacute;gales</a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
