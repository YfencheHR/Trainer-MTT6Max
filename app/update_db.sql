el proyecto est-- Add visuals column if it doesn't exist
ALTER TABLE made_hands ADD COLUMN IF NOT EXISTS visuals jsonb;

-- Update visuals for all hands
UPDATE made_hands SET visuals = '[{"cards":["A♠","K♠","Q♠","J♠","10♠"]}]' WHERE name = 'Escalera de Color Real';
UPDATE made_hands SET visuals = '[{"cards":["10♠","9♠","8♠","7♠","6♠"]}]' WHERE name = 'Escalera de Color';
UPDATE made_hands SET visuals = '[{"cards":["A♠","A♥","A♣","A♦"]}]' WHERE name = 'Póker';
UPDATE made_hands SET visuals = '[{"cards":["A♠","A♥","A♣","K♦","K♥"]}]' WHERE name = 'Full House';
UPDATE made_hands SET visuals = '[{"cards":["A♠","9♠","8♠","6♠","3♠"]}]' WHERE name = 'Color';
UPDATE made_hands SET visuals = '[{"cards":["10♠","9♠","8♠","7♠","6♠"]}]' WHERE name = 'Escalera';
UPDATE made_hands SET visuals = '[{"cards":["A♠","A♥","A♣"]}]' WHERE name = 'Tercera/Trips';
UPDATE made_hands SET visuals = '[{"cards":["A♠","A♥","K♣","K♦"]}]' WHERE name = 'Dos Parejas';
UPDATE made_hands SET visuals = '[{"cards":["A♠","A♥"],"separator":true},{"cards":["K♠","K♥"],"separator":true},{"cards":["Q♠","Q♥"],"separator":true},{"cards":["J♠","J♥"]}]' WHERE name = 'Pareja Alta';
UPDATE made_hands SET visuals = '[{"cards":["10♠","10♥"],"separator":true},{"cards":["9♠","9♥"],"separator":true},{"cards":["7♠","7♥"]}]' WHERE name = 'Pareja Media';
UPDATE made_hands SET visuals = '[{"cards":["6♠","6♥"],"separator":true},{"cards":["5♠","4♥"],"separator":true},{"cards":["3♠","3♥"],"separator":true},{"cards":["2♠","2♥"]}]' WHERE name = 'Pareja Baja';
UPDATE made_hands SET visuals = '[{"cards":["A♠","9♠","8♠","6♠","?♠"]}]' WHERE name = 'Proyecto de Color';
UPDATE made_hands SET visuals = '[{"cards":["?♥","10♠","9♠","8♠","7♠","?♥"]}]' WHERE name = 'Proyecto de Escalera Abierta';
UPDATE made_hands SET visuals = '[{"cards":["10♠","9♠","?♣","8♦","6♥"]}]' WHERE name = 'Proyecto de Escalera Barrigona';
UPDATE made_hands SET visuals = '[]' WHERE name = 'Proyecto Color + Escalera';
