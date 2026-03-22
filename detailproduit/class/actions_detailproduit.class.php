<?php
/* Copyright (C) 2025 Patrice GOURMELEN <pgourmelen@diamant-industrie.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/actions_detailproduit.class.php
 * \ingroup detailproduit
 * \brief   Compatibility alias - redirects to core/hooks/detailproduit.class.php
 *
 * Dolibarr's HookManager looks for class/actions_<modulename>.class.php
 * We redirect to core/hooks/ for cleaner organization.
 */

require_once __DIR__.'/../core/hooks/detailproduit.class.php';
