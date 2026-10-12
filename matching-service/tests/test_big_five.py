import unittest

from app.services.big_five import BigFiveScorer
from ipipneo.reverse import ReverseScored120


FIXTURE_ANSWERS = [
    5, 2, 3, 2, 5, 4, 1, 3, 5, 5,
    3, 2, 2, 4, 5, 2, 5, 4, 2, 5,
    2, 4, 5, 2, 4, 1, 2, 5, 4, 4,
    2, 3, 4, 5, 5, 4, 2, 2, 5, 5,
    2, 2, 4, 3, 4, 4, 4, 4, 5, 4,
    2, 4, 3, 3, 4, 5, 2, 4, 1, 3,
    1, 3, 1, 4, 4, 2, 2, 3, 5, 5,
    2, 4, 4, 4, 3, 1, 2, 4, 5, 5,
    3, 2, 2, 2, 5, 1, 4, 4, 2, 1,
    4, 2, 2, 4, 3, 2, 1, 3, 5, 5,
    1, 4, 2, 2, 4, 4, 3, 4, 4, 5,
    3, 2, 4, 5, 5, 1, 4, 2, 4, 4,
]


class BigFiveScorerTest(unittest.TestCase):
    def test_matches_five_factor_e_fixture(self):
        answers = {index: value for index, value in enumerate(FIXTURE_ANSWERS, start=1)}

        result = BigFiveScorer().compute(
            answers=answers,
            test_version="ipip-neo-120",
            age=65,
            sex="F",
        )

        self.assertEqual(result["test_version"], "ipip-neo-120")
        self.assertEqual(result["factors"], {
            "O": 24.29,
            "C": 1,
            "E": 32.93,
            "A": 1,
            "N": 67.59,
        })
        self.assertAlmostEqual(result["facets"]["imagination"], 21.44)
        self.assertAlmostEqual(result["facets"]["vulnerability"], 71.39)

    def test_supports_neutral_sex_norms(self):
        answers = {index: 3 for index in range(1, 121)}

        result = BigFiveScorer().compute(
            answers=answers,
            test_version="ipip-neo-120",
            age=30,
            sex="N",
        )

        self.assertEqual(set(result["factors"].keys()), {"O", "C", "E", "A", "N"})
        self.assertEqual(result["raw_scores"]["person"]["sex"], "N")

    def test_five_factor_e_applies_ipip_neo_120_reverse_scoring(self):
        answers = {
            "answers": [
                {"id_question": index, "id_select": 1}
                for index in range(1, 121)
            ]
        }

        reversed_answers = ReverseScored120(answers=answers)["answers"]

        self.assertEqual(reversed_answers[0]["id_select"], 1)
        self.assertEqual(reversed_answers[8]["id_select"], 5)
        self.assertEqual(reversed_answers[119]["id_select"], 5)


if __name__ == "__main__":
    unittest.main()
