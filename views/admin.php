<?php
/** @var array<string, mixed> $user */
/** @var array<string, int> $counts */
/** @var array<int, array<string, mixed>> $users */
/** @var array<int, array<string, mixed>> $adults */
/** @var array<int, array<string, mixed>> $students */
/** @var array<int, array<string, mixed>> $guardianLinks */
/** @var array<int, array<string, mixed>> $conversations */
/** @var array<int, array<string, mixed>> $channels */
/** @var array<int, array<string, mixed>> $reports */
/** @var array<int, array<string, mixed>> $notifications */
/** @var string $csrf */
/** @var string|null $flash */
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <p class="eyebrow">Administration</p>
        <h1>Good morning.</h1>
        <nav aria-label="Admin sections">
            <a class="active" href="#overview"><span>⌂</span> Overview</a>
            <a href="#people"><span>♙</span> People</a>
            <a href="#safeguarding"><span>◫</span> Safeguarding</a>
            <a href="#channels"><span>◇</span> Channels</a>
            <a href="#moderation"><span>≡</span> Moderation</a>
        </nav>
        <div class="sidebar-note">
            <strong>Signed in</strong>
            <p><?= $h($user['first_name'] ?? '') ?> <?= $h($user['last_name'] ?? '') ?></p>
            <form method="post" action="/logout">
                <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                <button class="link-button" type="submit">Sign out</button>
            </form>
        </div>
    </aside>
    <section class="admin-content" id="overview">
        <div class="admin-heading">
            <div><p class="eyebrow">Build 009</p><h2>Community overview</h2></div>
        </div>
        <?php if ($flash): ?>
            <div class="status-banner"><span>✓</span><div><strong>Action complete</strong><p><?= $h($flash) ?></p></div></div>
        <?php endif; ?>
        <div class="status-banner"><span>✓</span><div><strong>The database is connected</strong><p>Authentication, invitations, users, roles, and audit hooks are now live.</p></div><a href="/health">View health</a></div>
        <div class="metric-grid">
            <article><span>Members</span><strong><?= $h($counts['members'] ?? 0) ?></strong><small>Invited and active</small></article>
            <article><span>Students linked</span><strong><?= $h($counts['students'] ?? 0) ?></strong><small>Family setup next</small></article>
            <article><span>Active channels</span><strong><?= $h($counts['channels'] ?? 0) ?></strong><small>Announcements and posts</small></article>
            <article class="accent"><span>Safety alerts</span><strong><?= $h($counts['alerts'] ?? 0) ?></strong><small>Open reports</small></article>
        </div>
        <div class="admin-grid">
            <article class="panel" id="people">
                <div class="panel-heading"><div><p class="eyebrow">People</p><h3>Invite a member</h3></div><span>Secure token</span></div>
                <form class="stack-form" method="post" action="/admin/invitations">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label>Email <input required type="email" name="email" autocomplete="email"></label>
                    <div class="form-row">
                        <label>First name <input required name="first_name" autocomplete="given-name"></label>
                        <label>Last name <input required name="last_name" autocomplete="family-name"></label>
                    </div>
                    <label class="check-row"><input type="checkbox" name="is_student" value="1"> Student account</label>
                    <fieldset>
                        <legend>Roles</legend>
                        <label><input type="checkbox" name="roles[]" value="general_member" checked> General member</label>
                        <label><input type="checkbox" name="roles[]" value="guardian"> Parent / Guardian</label>
                        <label><input type="checkbox" name="roles[]" value="student"> Student</label>
                        <label><input type="checkbox" name="roles[]" value="volunteer"> Volunteer</label>
                        <label><input type="checkbox" name="roles[]" value="instructor"> Instructor</label>
                        <label><input type="checkbox" name="roles[]" value="production_staff"> Production staff</label>
                        <label><input type="checkbox" name="roles[]" value="administrator"> Administrator</label>
                    </fieldset>
                    <button type="submit">Create invite</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Directory</p><h3>Current accounts</h3></div><span><?= count($users) ?> total</span></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Roles</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $account): ?>
                            <tr>
                                <td><?= $h(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')) ?></td>
                                <td><?= $h($account['email'] ?? '') ?></td>
                                <td><?= $h($account['status'] ?? '') ?></td>
                                <td><?= $h($account['roles'] ?? 'No roles') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
        <div class="admin-grid safeguarding-grid" id="safeguarding">
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Families</p><h3>Link guardian to student</h3></div><span>Approved</span></div>
                <form class="stack-form" method="post" action="/admin/guardian-links">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label>Guardian
                        <select required name="guardian_user_id">
                            <option value="">Choose active adult</option>
                            <?php foreach ($adults as $adult): ?>
                                <option value="<?= $h($adult['id'] ?? '') ?>"><?= $h(($adult['first_name'] ?? '') . ' ' . ($adult['last_name'] ?? '') . ' - ' . ($adult['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Student
                        <select required name="student_user_id">
                            <option value="">Choose active student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $h($student['id'] ?? '') ?>"><?= $h(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '') . ' - ' . ($student['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Relationship label <input name="relationship_label" placeholder="Parent, guardian, caregiver"></label>
                    <button type="submit">Approve family link</button>
                </form>
                <div class="compact-list">
                    <?php foreach ($guardianLinks as $link): ?>
                        <p><strong><?= $h(($link['guardian_first_name'] ?? '') . ' ' . ($link['guardian_last_name'] ?? '')) ?></strong> sees <?= $h(($link['student_first_name'] ?? '') . ' ' . ($link['student_last_name'] ?? '')) ?> <span><?= $h($link['status'] ?? '') ?></span></p>
                    <?php endforeach; ?>
                    <?php if ($guardianLinks === []): ?><p>No guardian links yet.</p><?php endif; ?>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Messaging</p><h3>Create safeguarded conversation</h3></div><span>Server enforced</span></div>
                <form class="stack-form" method="post" action="/admin/safeguarded-conversations">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label>Adult
                        <select required name="adult_user_id">
                            <option value="">Choose active adult</option>
                            <?php foreach ($adults as $adult): ?>
                                <option value="<?= $h($adult['id'] ?? '') ?>"><?= $h(($adult['first_name'] ?? '') . ' ' . ($adult['last_name'] ?? '') . ' - ' . ($adult['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Student
                        <select required name="student_user_id">
                            <option value="">Choose active student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $h($student['id'] ?? '') ?>"><?= $h(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '') . ' - ' . ($student['email'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit">Create with guardians</button>
                </form>
                <div class="compact-list">
                    <?php foreach ($conversations as $conversation): ?>
                        <p><strong><a href="/conversations?id=<?= $h($conversation['id'] ?? '') ?>">#<?= $h($conversation['id'] ?? '') ?> <?= $h($conversation['type'] ?? '') ?></a></strong> <?= $h($conversation['participants'] ?? '') ?></p>
                    <?php endforeach; ?>
                    <?php if ($conversations === []): ?><p>No safeguarded conversations yet.</p><?php endif; ?>
                </div>
            </article>
        </div>
        <div class="admin-grid" id="channels">
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Channels</p><h3>Create channel</h3></div><span>Community</span></div>
                <form class="stack-form" method="post" action="/admin/channels">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label>Name <input required name="name" placeholder="Announcements"></label>
                    <label>Description <input name="description" placeholder="Important CTSMD updates"></label>
                    <div class="form-row">
                        <label>Type
                            <select required name="type">
                                <option value="announcement">Announcement</option>
                                <option value="discussion">Discussion</option>
                                <option value="group">Production/group</option>
                                <option value="parent">Parent</option>
                                <option value="staff">Staff-only</option>
                                <option value="resource">Resource/read-only</option>
                            </select>
                        </label>
                        <label>Posting
                            <select required name="posting_policy">
                                <option value="admins">Admins only</option>
                                <option value="members">Members</option>
                                <option value="selected_roles">Selected roles</option>
                            </select>
                        </label>
                    </div>
                    <button type="submit">Create channel</button>
                </form>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Publish</p><h3>Post to channel</h3></div><span>Pinned ready</span></div>
                <form class="stack-form" method="post" action="/admin/channel-posts">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <label>Channel
                        <select required name="channel_id">
                            <option value="">Choose channel</option>
                            <?php foreach ($channels as $channel): ?>
                                <option value="<?= $h($channel['id'] ?? '') ?>"><?= $h($channel['name'] ?? '') ?> - <?= $h($channel['type'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Post <textarea required name="body" rows="5"></textarea></label>
                    <label class="check-row"><input type="checkbox" name="is_pinned" value="1"> Pin this post</label>
                    <button type="submit">Publish post</button>
                </form>
                <form class="stack-form" method="post" action="/admin/channel-members">
                    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                    <div class="form-row">
                        <label>Channel
                            <select required name="channel_id">
                                <option value="">Choose channel</option>
                                <?php foreach ($channels as $channel): ?>
                                    <option value="<?= $h($channel['id'] ?? '') ?>"><?= $h($channel['name'] ?? '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Member
                            <select required name="user_id">
                                <option value="">Choose member</option>
                                <?php foreach ($users as $account): ?>
                                    <?php if (($account['status'] ?? '') === 'active'): ?>
                                        <option value="<?= $h($account['id'] ?? '') ?>"><?= $h(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '') . ' - ' . ($account['email'] ?? '')) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <label class="check-row"><input type="checkbox" name="can_post" value="1"> Allow posting when selected roles are required</label>
                    <button type="submit">Update channel member</button>
                </form>
                <div class="compact-list">
                    <?php foreach ($channels as $channel): ?>
                        <p><strong><a href="/channels?id=<?= $h($channel['id'] ?? '') ?>"><?= $h($channel['name'] ?? '') ?></a></strong><?= $h($channel['description'] ?? '') ?> <span><?= $h($channel['post_count'] ?? 0) ?> posts</span></p>
                    <?php endforeach; ?>
                    <?php if ($channels === []): ?><p>No channels yet.</p><?php endif; ?>
                </div>
            </article>
        </div>
        <div class="admin-grid" id="moderation">
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Moderation</p><h3>Content reports</h3></div><span><?= count($reports) ?> recent</span></div>
                <div class="compact-list">
                    <?php foreach ($reports as $report): ?>
                        <form class="report-row" method="post" action="/admin/reports/status">
                            <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                            <input type="hidden" name="report_id" value="<?= $h($report['id'] ?? '') ?>">
                            <p><strong>#<?= $h($report['id'] ?? '') ?> <?= $h($report['reason'] ?? '') ?></strong><?= $h($report['subject_type'] ?? '') ?> #<?= $h($report['subject_id'] ?? '') ?> reported by <?= $h(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')) ?> <span><?= $h($report['status'] ?? '') ?></span></p>
                            <?php if (!empty($report['details'])): ?><p><?= $h($report['details']) ?></p><?php endif; ?>
                            <select name="status">
                                <option value="reviewing">Reviewing</option>
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                                <option value="open">Open</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    <?php endforeach; ?>
                    <?php if ($reports === []): ?><p>No reports yet.</p><?php endif; ?>
                </div>
            </article>
            <article class="panel">
                <div class="panel-heading"><div><p class="eyebrow">Notifications</p><h3>Pending outbox</h3></div><span><?= count($notifications) ?> pending</span></div>
                <div class="compact-list">
                    <?php foreach ($notifications as $notice): ?>
                        <p><strong><?= $h($notice['type'] ?? '') ?></strong><?= $h(($notice['first_name'] ?? '') . ' ' . ($notice['last_name'] ?? '')) ?> via <?= $h($notice['channel'] ?? '') ?> <span><?= $h($notice['status'] ?? '') ?></span></p>
                    <?php endforeach; ?>
                    <?php if ($notifications === []): ?><p>No pending notifications.</p><?php endif; ?>
                </div>
            </article>
        </div>
    </section>
</div>
